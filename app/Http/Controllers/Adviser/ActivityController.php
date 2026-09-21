<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Jobs\NotifyMembersOfActivity;
use App\Jobs\NotifyOfficersOfActivityDecision;
use App\Jobs\SyncToGoogleCalendars;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\GoogleCalendarEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The club adviser's activity review queue (POLICY.md §Event Creation).
 *
 * Internal meetings never arrive here — they are scheduled the moment the officer
 * submits them. Everything else (ACLE, community involvement, campus resource use,
 * other external activity) waits as `pending_approval` until the adviser decides,
 * and approval is the point at which the activity becomes real: members are told,
 * and it is copied onto their calendars.
 *
 * Every action is scoped to the adviser's own club. Cross-club access is blocked at
 * the route middleware and again here (POLICY.md §Data Privacy), so an adviser cannot
 * reach another club's activity by guessing its id.
 */
class ActivityController extends Controller
{
    private function adviserClub(): ?Club
    {
        return Club::where('adviser_id', auth_user_id())->first();
    }

    /** The adviser's own club's activity, or 403. */
    private function ownActivity(ClubActivity $event): ClubActivity
    {
        $club = $this->adviserClub();

        if (! $club || $event->club_id !== $club->id) {
            abort(403);
        }

        return $event;
    }

    public function review(): View
    {
        $club = $this->adviserClub();

        $pending = $club
            ? ClubActivity::where('club_id', $club->id)
                ->where('approval_status', 'pending_approval')
                ->with('club', 'creator')
                ->orderBy('date')
                ->get()
            : collect();

        $reviewed = $club
            ? ClubActivity::where('club_id', $club->id)
                ->whereIn('approval_status', ['approved', 'rejected'])
                ->with('club', 'creator')
                ->orderByDesc('approved_at')
                ->take(20)
                ->get()
            : collect();

        return view('adviser.activities.review', compact('club', 'pending', 'reviewed'));
    }

    public function show(ClubActivity $event): View
    {
        $this->ownActivity($event);

        $event->load('club', 'creator', 'approvedBy', 'changeLogs.changedBy');

        return view('adviser.activities.show', compact('event'));
    }

    public function approve(ClubActivity $event): RedirectResponse
    {
        $this->ownActivity($event);

        $event->update([
            'approval_status' => 'approved',
            'approved_by'     => auth_user_id(),
            'approved_at'     => now(),
            'review_remarks'  => null,
        ]);

        NotifyOfficersOfActivityDecision::dispatch($event->id, 'approved');

        // Now that it is confirmed, the members can be told — this is the moment
        // an approval-requiring activity becomes real to them.
        NotifyMembersOfActivity::dispatch($event->id, NotifyMembersOfActivity::APPROVED);

        SyncToGoogleCalendars::dispatch(GoogleCalendarEvent::ACTIVITY, $event->id); // onto members' Google Calendars

        return back()->with('success', 'Activity approved.');
    }

    public function reject(Request $request, ClubActivity $event): RedirectResponse
    {
        $this->ownActivity($event);

        $validated = $request->validate([
            'review_remarks' => ['required', 'string', 'min:5'],
        ], [
            'review_remarks.required' => 'Please provide a reason for rejection.',
        ]);

        $event->update([
            'approval_status' => 'rejected',
            'review_remarks'  => $validated['review_remarks'],
            'approved_by'     => auth_user_id(),
            'approved_at'     => null,
        ]);

        NotifyOfficersOfActivityDecision::dispatch($event->id, 'rejected');
        SyncToGoogleCalendars::dispatch(GoogleCalendarEvent::ACTIVITY, $event->id); // takes it off any calendar it was on

        return back()->with('success', 'Activity rejected.');
    }

    public function downloadLetter(ClubActivity $event)
    {
        $this->ownActivity($event);

        if (! $event->approval_letter_path) {
            abort(404);
        }

        return Storage::disk('local')->download($event->approval_letter_path);
    }
}
