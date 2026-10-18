<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Jobs\NotifyMembersOfActivity;
use App\Jobs\NotifyOfficersOfActivityDecision;
use App\Jobs\SyncToGoogleCalendars;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\GoogleCalendarEvent;
use App\Models\User;
use App\Services\BookingLetter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The club adviser's activity review queue (POLICY.md §Event Creation).
 *
 * Every activity an officer adds, internal meetings included, goes first to the Vice
 * President for Administration with a room booking letter, and reaches the adviser once
 * the VPA has approved it. The
 * adviser's approval is the last one, and the point at which the activity becomes
 * real: members are told, and it is copied onto their calendars. Approving signs the
 * letter under "Noted by".
 *
 * Every action is scoped to the adviser's own club. Cross-club access is blocked at
 * the route middleware and again here (POLICY.md §Data Privacy), so an adviser cannot
 * reach another club's activity by guessing its id.
 */
class ActivityController extends Controller
{
    public function __construct(private BookingLetter $letters) {}

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

    /**
     * Why an activity cannot be decided now, or null when it can: it must be past the VPA
     * and in one of $from (an adviser may also withdraw an approval they gave).
     */
    private function notDecidable(ClubActivity $event, array $from = ['pending_approval']): ?string
    {
        if (in_array($event->approval_status, $from, true)) {
            return null;
        }

        return $event->isAwaitingVpa()
            ? 'The Vice President for Administration has not approved this letter yet.'
            : 'This activity has already been decided.';
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

        // Not the adviser's to decide yet, but theirs to know about.
        $withVpa = $club
            ? ClubActivity::where('club_id', $club->id)
                ->where('approval_status', 'pending_vpa')
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

        return view('adviser.activities.review', compact('club', 'pending', 'withVpa', 'reviewed'));
    }

    public function show(ClubActivity $event): View
    {
        $this->ownActivity($event);

        $event->load('club', 'creator', 'approvedBy', 'vpaReviewer', 'changeLogs.changedBy');

        $letter = $event->hasBookingLetter() ? $this->letters->compose($event) : null;

        $hasSignature = (bool) User::find(auth_user_id())?->signature_path;

        return view('adviser.activities.show', compact('event', 'letter', 'hasSignature'));
    }

    public function approve(ClubActivity $event): RedirectResponse
    {
        $this->ownActivity($event);

        if ($error = $this->notDecidable($event)) {
            return back()->with('error', $error);
        }

        $changes = [
            'approval_status' => 'approved',
            'approved_by'     => auth_user_id(),
            'approved_at'     => now(),
            'review_remarks'  => null,
        ];

        // Confirming signs the room booking letter under "Noted by".
        if ($event->hasBookingLetter() && ($adviser = User::find(auth_user_id()))) {
            $changes['letter_signatures'] = array_merge($event->letter_signatures ?? [], [
                'adviser' => $this->letters->sign($adviser, BookingLetter::adviserPosition($event->club)),
            ]);
        }

        $event->update($changes);

        AuditLog::record('activity.approved', $event, ['club_id' => $event->club_id]);

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

        if ($error = $this->notDecidable($event, ['pending_approval', 'approved'])) {
            return back()->with('error', $error);
        }

        $event->update([
            'approval_status' => 'rejected',
            'review_remarks'  => $validated['review_remarks'],
            'approved_by'     => auth_user_id(),
            'approved_at'     => null,
        ]);

        AuditLog::record('activity.rejected', $event, ['club_id' => $event->club_id]);

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

    /** The activity's room booking letter as a PDF, with every signature given so far. */
    public function bookingLetter(ClubActivity $event)
    {
        $this->ownActivity($event);

        if (! $event->hasBookingLetter()) {
            abort(404);
        }

        return $this->letters->pdf($event)->download($this->letters->filename($event));
    }
}
