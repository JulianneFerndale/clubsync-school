<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\FeePayment;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    /** The panels of the Activity page, in the order the tab bar shows them. The first opens by default. */
    private const TABS = ['upcoming', 'attendance', 'fees'];

    public function index(Request $request): View
    {
        $userId = auth_user_id();

        // Which panel is open lives in the URL (?tab=fees), not in browser state. The
        // page opts into live refresh, which re-fetches this same URL and swaps the
        // markup in, so a tab held only on the client would snap back to Attendance
        // every time an update arrived — and the tab would not survive back/forward
        // or a shared link either.
        $tab = in_array($request->query('tab'), self::TABS, true)
            ? $request->query('tab')
            : self::TABS[0];

        // What is coming up in the member's clubs: the same activities they are notified
        // about (NotifyMembersOfActivity) — confirmed, and aimed at their year level. When
        // one is scheduled or approved, that notification refreshes this page in place.
        $clubIds   = ClubMember::where('user_id', $userId)->where('status', 'active')->pluck('club_id');
        $yearLevel = User::whereKey($userId)->value('year_level');

        $upcoming = $clubIds->isEmpty() ? collect() : ClubActivity::whereIn('club_id', $clubIds)
            ->upcoming()
            ->confirmed()
            ->orderBy('time_start')
            ->with('club:id,name,acronym')
            ->get()
            ->filter(fn (ClubActivity $a) => $a->reachesYearLevel($yearLevel))
            ->values();

        $attendance = Attendance::where('user_id', $userId)
            ->with(['activity.club'])
            ->get()
            // Newest activity first, and within one day the latest check-in first.
            // Ordering here rather than in SQL keys the list to the activity's date,
            // which is what the month headings group by; a record whose activity was
            // deleted has no date to sort on and sinks to the bottom.
            ->sortByDesc(fn (Attendance $a) => [
                $a->activity?->date?->timestamp ?? 0,
                $a->time_in?->timestamp ?? 0,
            ]);

        $attendanceCount   = $attendance->count();
        $attendanceByMonth = $attendance->groupBy(
            fn (Attendance $a) => $a->activity?->date?->format('F Y') ?? 'Unknown'
        );

        $feePayments = FeePayment::where('user_id', $userId)
            ->with(['fee.club'])
            ->orderByDesc('created_at')
            ->get();

        // A cancelled fee is owed by no one: it leaves Pending and Paid for a list of its
        // own, where a member who had already paid it can still find the receipt.
        [$cancelledFees, $openFees] = $feePayments->partition(fn (FeePayment $p) => (bool) $p->fee?->isCancelled());

        $pendingFees = $openFees->where('status', 'pending');
        $paidFees    = $openFees->where('status', 'paid');
        $amountDue   = $pendingFees->sum(fn (FeePayment $p) => (float) ($p->fee?->amount ?? 0));

        return view('member.activity', compact(
            'tab',
            'upcoming',
            'attendanceByMonth',
            'attendanceCount',
            'pendingFees',
            'paidFees',
            'cancelledFees',
            'amountDue',
        ));
    }

    /**
     * A member's own official receipt, as the same PDF the treasurer hands over.
     *
     * Scoped to the signed-in member: the payment id in the URL is never enough on its
     * own, or one member could pull another's receipt — which carries their name and
     * EDP number — out of the series by guessing a number.
     */
    public function receipt(FeePayment $payment)
    {
        if ($payment->user_id !== auth_user_id()) {
            abort(403);
        }

        if (! $payment->hasReceipt()) {
            abort(404);
        }

        $payment->load('user', 'confirmer', 'fee.club');

        $fee  = $payment->fee;
        $club = $fee?->club;

        // The receipt names the club that collected the money; without it there is
        // nothing valid to issue.
        if (! $fee || ! $club) {
            abort(404);
        }

        return Pdf::loadView('fees.receipt-pdf', compact('payment', 'fee', 'club'))
            ->download('receipt-' . $payment->receipt_number . '.pdf');
    }
}
