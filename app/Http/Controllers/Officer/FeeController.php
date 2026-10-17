<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Jobs\NotifyMembersOfFee;
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
    private const CLOSED = 'This fee was cancelled, so it can no longer be changed or paid.';

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

        NotifyMembersOfFee::dispatch($fee->id, NotifyMembersOfFee::CREATED, auth_user_id());

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

    public function edit(Fee $fee): View|RedirectResponse
    {
        $club = $this->officerClub();

        if (! $club || $fee->club_id !== $club->id) {
            abort(403);
        }

        if ($fee->isCancelled()) {
            return redirect()->route('officer.fees.show', $fee)->with('error', self::CLOSED);
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

        if ($fee->isCancelled()) {
            return redirect()->route('officer.fees.show', $fee)->with('error', self::CLOSED);
        }

        $fee->fill($validated);

        // Members hear about what changes how much they owe or by when; a reworded
        // title or academic period is not worth a notification.
        $material = $fee->isDirty('amount') || $fee->isDirty('due_date');

        $fee->save();

        SyncToGoogleCalendars::dispatch(GoogleCalendarEvent::FEE, $fee->id);

        if ($material) {
            NotifyMembersOfFee::dispatch($fee->id, NotifyMembersOfFee::UPDATED, auth_user_id());
        }

        return redirect()->route('officer.fees.show', $fee)->with('success', 'Fee updated.');
    }

    /**
     * Move the due date later. The members who still owe the fee are told the new date
     * (and why, when a reason is given), and their calendar copies move with it.
     */
    public function postpone(Request $request, Fee $fee): RedirectResponse
    {
        $club = $this->officerClub();

        if (! $club || $fee->club_id !== $club->id) {
            abort(403);
        }

        if ($fee->isCancelled()) {
            return back()->with('error', self::CLOSED);
        }

        $validated = $request->validate([
            'due_date' => ['required', 'date', 'after:' . $fee->due_date->toDateString(), 'after_or_equal:today'],
            'reason'   => ['nullable', 'string', 'max:500'],
        ], [
            'due_date.required'       => 'Choose the new due date.',
            'due_date.after'          => 'A postponed fee must be due later than ' . $fee->due_date->format('F j, Y') . '.',
            'due_date.after_or_equal' => 'The new due date cannot be in the past.',
        ]);

        $fee->forceFill([
            'previous_due_date' => $fee->due_date,
            'due_date'          => $validated['due_date'],
            'status'            => Fee::POSTPONED,
            'status_reason'     => $validated['reason'] ?? null,
            'status_changed_at' => now(),
        ])->save();

        SyncToGoogleCalendars::dispatch(GoogleCalendarEvent::FEE, $fee->id);
        NotifyMembersOfFee::dispatch($fee->id, NotifyMembersOfFee::POSTPONED, auth_user_id());

        return redirect()->route('officer.fees.show', $fee)
            ->with('success', 'Fee postponed to ' . $fee->due_date->format('F j, Y') . '. The members who still owe it have been told.');
    }

    /**
     * Stop collecting a fee. Nobody owes it any more; payments already made stay on
     * record with their receipts, and those members are told to see the treasurer
     * about a refund. Cancelling is final: the fee can no longer be edited or paid.
     */
    public function cancel(Request $request, Fee $fee): RedirectResponse
    {
        $club = $this->officerClub();

        if (! $club || $fee->club_id !== $club->id) {
            abort(403);
        }

        if ($fee->isCancelled()) {
            return back()->with('error', self::CLOSED);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $fee->forceFill([
            'status'            => Fee::CANCELLED,
            'status_reason'     => $validated['reason'] ?? null,
            'status_changed_at' => now(),
        ])->save();

        // Off every calendar it is still on: GoogleCalendarSync has no entry for a cancelled fee.
        SyncToGoogleCalendars::dispatch(GoogleCalendarEvent::FEE, $fee->id);
        NotifyMembersOfFee::dispatch($fee->id, NotifyMembersOfFee::CANCELLED, auth_user_id());

        return redirect()->route('officer.fees.show', $fee)
            ->with('success', 'Fee cancelled. Its members have been told.');
    }

    public function markPaid(Fee $fee, User $user): RedirectResponse
    {
        $club = $this->officerClub();

        if (! $club || $fee->club_id !== $club->id) {
            abort(403);
        }

        if ($fee->isCancelled()) {
            return back()->with('error', self::CLOSED);
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

        NotifyMembersOfFee::dispatch($fee->id, NotifyMembersOfFee::PAID, auth_user_id(), $user->id);

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

        if ($fee->isCancelled()) {
            return back()->with('error', self::CLOSED);
        }

        FeePayment::where('fee_id', $fee->id)
            ->where('user_id', $user->id)
            ->update([
                'status'       => 'pending',
                'confirmed_by' => null,
                'confirmed_at' => null,
            ]);

        SyncGoogleCalendarUsers::dispatch(GoogleCalendarEvent::FEE, $fee->id, [$user->id]);

        NotifyMembersOfFee::dispatch($fee->id, NotifyMembersOfFee::UNPAID, auth_user_id(), $user->id);

        return back();
    }
}
