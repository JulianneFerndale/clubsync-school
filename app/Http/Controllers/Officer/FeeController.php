<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Jobs\SyncGoogleCalendarUsers;
use App\Jobs\SyncToGoogleCalendars;
use App\Models\ClubMember;
use App\Models\ClubOfficer;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\GoogleCalendarEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FeeController extends Controller
{
    private function officerClub(): ?\App\Models\Club
    {
        return ClubOfficer::where('user_id', auth_user_id())
            ->with('club')
            ->first()
            ?->club;
    }

    public function index(): View
    {
        $club = $this->officerClub();

        $fees = $club
            ? Fee::where('club_id', $club->id)
                ->withCount(['payments as paid_count'   => fn ($q) => $q->where('status', 'paid')])
                ->withCount(['payments as total_count'])
                ->orderByDesc('created_at')
                ->get()
            : collect();

        return view('officer.fees.index', compact('club', 'fees'));
    }

    public function create(): View
    {
        $club = $this->officerClub();

        return view('officer.fees.create', compact('club'));
    }

    public function store(Request $request): RedirectResponse
    {
        $club = $this->officerClub();

        if (! $club) {
            return back()->withErrors(['club' => 'You are not assigned to any club.']);
        }

        $validated = $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'due_date'        => ['required', 'date', 'after_or_equal:today'],
            'academic_period' => ['required', 'string', 'max:50'],
        ], [
            'title.required'           => 'Fee title is required.',
            'amount.required'          => 'Amount is required.',
            'amount.min'               => 'Amount must be greater than zero.',
            'due_date.required'        => 'Due date is required.',
            'due_date.after_or_equal'  => 'Due date must be today or in the future.',
            'academic_period.required' => 'Academic period is required.',
        ]);

        // Wrap the fee + its per-member payment rows in a transaction so that a
        // cancelled/aborted request (e.g. the user hits "Stop") never leaves a
        // fee with only some members assigned — it all commits or none of it does.
        $memberIds = ClubMember::where('club_id', $club->id)
            ->where('status', 'active')
            ->pluck('user_id');

        $fee = DB::transaction(function () use ($club, $validated, $memberIds) {
            $fee = Fee::create([
                'club_id'         => $club->id,
                'title'           => $validated['title'],
                'amount'          => $validated['amount'],
                'due_date'        => $validated['due_date'],
                'academic_period' => $validated['academic_period'],
            ]);

            // Auto-create pending payment records for every active club member
            foreach ($memberIds as $userId) {
                FeePayment::firstOrCreate(
                    ['fee_id' => $fee->id, 'user_id' => $userId],
                    ['status' => 'pending'],
                );
            }

            return $fee;
        });

        // The due date goes onto the Google Calendar of every member who owes it.
        SyncToGoogleCalendars::dispatch(GoogleCalendarEvent::FEE, $fee->id);

        return redirect()->route('officer.fees.show', $fee)
            ->with('success', 'Fee created and assigned to ' . $memberIds->count() . ' active members.');
    }

    public function show(Fee $fee): View
    {
        $club = $this->officerClub();

        if (! $club || $fee->club_id !== $club->id) {
            abort(403);
        }

        $payments = FeePayment::where('fee_id', $fee->id)
            ->with('user')
            ->get()
            ->sortBy(fn ($p) => $p->user?->last_name . $p->user?->first_name);

        $paidCount    = $payments->where('status', 'paid')->count();
        $pendingCount = $payments->where('status', 'pending')->count();

        return view('officer.fees.show', compact('club', 'fee', 'payments', 'paidCount', 'pendingCount'));
    }

    public function edit(Fee $fee): View
    {
        $club = $this->officerClub();

        if (! $club || $fee->club_id !== $club->id) {
            abort(403);
        }

        return view('officer.fees.edit', compact('club', 'fee'));
    }

    public function update(Request $request, Fee $fee): RedirectResponse
    {
        $club = $this->officerClub();

        if (! $club || $fee->club_id !== $club->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'due_date'        => ['required', 'date'],
            'academic_period' => ['required', 'string', 'max:50'],
        ], [
            'title.required'           => 'Fee title is required.',
            'amount.required'          => 'Amount is required.',
            'amount.min'               => 'Amount must be greater than zero.',
            'due_date.required'        => 'Due date is required.',
            'academic_period.required' => 'Academic period is required.',
        ]);

        $fee->update($validated);

        SyncToGoogleCalendars::dispatch(GoogleCalendarEvent::FEE, $fee->id);

        return redirect()->route('officer.fees.show', $fee)->with('success', 'Fee updated.');
    }

    public function markPaid(Fee $fee, User $user): RedirectResponse
    {
        $club = $this->officerClub();

        if (! $club || $fee->club_id !== $club->id) {
            abort(403);
        }

        $payment = FeePayment::firstOrNew([
            'fee_id'  => $fee->id,
            'user_id' => $user->id,
        ]);

        $payment->forceFill([
            'status'       => 'paid',
            'confirmed_by' => auth_user_id(),
            'confirmed_at' => now(),
        ])->save();

        // The receipt is issued at the moment the payment is confirmed, so the member
        // can be handed one there and then. A payment that was undone and confirmed
        // again keeps the number it already had.
        $payment->issueReceiptNumber();

        // Paid up: the due-date reminder comes off this member's Google Calendar.
        SyncGoogleCalendarUsers::dispatch(GoogleCalendarEvent::FEE, $fee->id, [$user->id]);

        return back();
    }

    /**
     * The receipt for a confirmed payment, as a PDF the treasurer can print or send.
     *
     * Only for a payment that is actually paid: undoing a payment leaves its number on
     * the record so re-confirming reuses it, but there is nothing to receipt in the
     * meantime, so the receipt stops being available until it is confirmed again.
     */
    public function receipt(Fee $fee, User $user)
    {
        $club = $this->officerClub();

        if (! $club || $fee->club_id !== $club->id) {
            abort(403);
        }

        $payment = FeePayment::where('fee_id', $fee->id)
            ->where('user_id', $user->id)
            ->with('user', 'confirmer')
            ->first();

        if (! $payment?->hasReceipt()) {
            abort(404);
        }

        $fee->load('club');

        return Pdf::loadView('fees.receipt-pdf', compact('payment', 'fee', 'club'))
            ->download('receipt-' . $payment->receipt_number . '.pdf');
    }

    public function markUnpaid(Fee $fee, User $user): RedirectResponse
    {
        $club = $this->officerClub();

        if (! $club || $fee->club_id !== $club->id) {
            abort(403);
        }

        FeePayment::where('fee_id', $fee->id)
            ->where('user_id', $user->id)
            ->update([
                'status'       => 'pending',
                'confirmed_by' => null,
                'confirmed_at' => null,
            ]);

        SyncGoogleCalendarUsers::dispatch(GoogleCalendarEvent::FEE, $fee->id, [$user->id]);

        return back();
    }
}
