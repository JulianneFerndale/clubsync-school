<?php

namespace App\Http\Controllers\VPA;

use App\Http\Controllers\Controller;
use App\Jobs\NotifyMembersOfActivity;
use App\Jobs\NotifyOfficersOfActivityDecision;
use App\Jobs\SyncToGoogleCalendars;
use App\Models\AuditLog;
use App\Models\ClubActivity;
use App\Models\GoogleCalendarEvent;
use App\Models\User;
use App\Services\BookingLetter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Vice President for Administration's room booking letters (POLICY.md §Event
 * Creation). Every activity comes here once the club adviser has approved it, with a
 * letter the club officer and the adviser signed. The VPA's approval is the last one: it
 * signs the letter and confirms the activity, so members are told and it goes onto their
 * calendars. Or the VPA returns it with remarks.
 *
 * The account may be run by the SSG for the VPA. The VPA decides for every club — that
 * is the office's job — but sees only what a booking letter carries: the activity's
 * details, the letter and its signatures, never a club's members or records.
 */
class LetterController extends Controller
{
    private const FILTERS = ['pending', 'approved', 'returned', 'all'];

    public function __construct(private BookingLetter $letters) {}

    public function index(Request $request): View
    {
        $filter = in_array($request->query('status'), self::FILTERS, true) ? $request->query('status') : 'pending';

        $letters = ClubActivity::reachedVpa()
            ->with('club', 'creator')
            ->when($filter === 'pending', fn ($q) => $q->where('approval_status', 'pending_vpa')->orderBy('date')->orderBy('time_start'))
            ->when($filter === 'approved', fn ($q) => $q->where('vpa_decision', 'approved')->orderByDesc('vpa_reviewed_at'))
            ->when($filter === 'returned', fn ($q) => $q->where('vpa_decision', 'rejected')->orderByDesc('vpa_reviewed_at'))
            ->when($filter === 'all', fn ($q) => $q->orderByDesc('created_at'))
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'pending'  => ClubActivity::where('approval_status', 'pending_vpa')->count(),
            'approved' => ClubActivity::where('vpa_decision', 'approved')->count(),
            'returned' => ClubActivity::where('vpa_decision', 'rejected')->count(),
        ];

        return view('vpa.letters.index', compact('letters', 'filter', 'counts'));
    }

    public function show(ClubActivity $event): View
    {
        $this->letterOnly($event);

        $event->load('club', 'creator', 'vpaReviewer', 'approvedBy');

        $letter = $this->letters->compose($event);

        // Re-checked as the VPA reads it: the room should still be free for this slot.
        $conflict = $event->isAwaitingVpa()
            ? ClubActivity::findVenueConflict($event->venue, $event->date->toDateString(), (string) $event->time_start, (string) $event->time_end, $event->id)
            : null;

        $hasSignature = (bool) User::find(auth_user_id())?->signature_path;

        return view('vpa.letters.show', compact('event', 'letter', 'conflict', 'hasSignature'));
    }

    public function approve(ClubActivity $event): RedirectResponse
    {
        $this->letterOnly($event);

        if (! $event->isAwaitingVpa()) {
            return back()->with('error', 'This letter has already been decided.');
        }

        $vpa = User::findOrFail(auth_user_id());

        $signatures = $event->letter_signatures ?? [];
        $signatures['vpa'] = $this->letters->sign($vpa, config('vpa.title'), config('vpa.name'));

        $event->update([
            'approval_status'   => 'approved',
            'vpa_decision'      => 'approved',
            'vpa_reviewed_by'   => $vpa->id,
            'vpa_reviewed_at'   => now(),
            'review_remarks'    => null,
            'letter_signatures' => $signatures,
        ]);

        AuditLog::record('activity.letter.vpa_approved', $event, ['club_id' => $event->club_id, 'venue' => $event->venue]);

        NotifyOfficersOfActivityDecision::dispatch($event->id, NotifyOfficersOfActivityDecision::VPA_APPROVED);

        // Confirmed now, so the members can be told — this is the moment the activity
        // becomes real to them — and it goes onto their Google Calendars.
        NotifyMembersOfActivity::dispatch($event->id, NotifyMembersOfActivity::APPROVED);
        SyncToGoogleCalendars::dispatch(GoogleCalendarEvent::ACTIVITY, $event->id);

        return redirect()->route('vpa.letters.index')
            ->with('success', "Approved and signed. \"{$event->title}\" is confirmed, and the club's members have been told.");
    }

    public function reject(Request $request, ClubActivity $event): RedirectResponse
    {
        $this->letterOnly($event);

        $validated = $request->validate([
            'review_remarks' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'review_remarks.required' => 'Say why the letter is not approved, so the club can fix it.',
            'review_remarks.min'      => 'Say why the letter is not approved, so the club can fix it.',
        ]);

        if (! $event->isAwaitingVpa()) {
            return back()->with('error', 'This letter has already been decided.');
        }

        $event->update([
            'approval_status' => 'rejected',
            'vpa_decision'    => 'rejected',
            'vpa_reviewed_by' => auth_user_id(),
            'vpa_reviewed_at' => now(),
            'review_remarks'  => $validated['review_remarks'],
        ]);

        AuditLog::record('activity.letter.vpa_rejected', $event, ['club_id' => $event->club_id]);

        NotifyOfficersOfActivityDecision::dispatch($event->id, NotifyOfficersOfActivityDecision::VPA_REJECTED);

        return redirect()->route('vpa.letters.index')
            ->with('success', "Letter returned to {$event->club?->name} with your remarks. The room is free again.");
    }

    public function pdf(ClubActivity $event)
    {
        $this->letterOnly($event);

        return $this->letters->pdf($event)->download($this->letters->filename($event));
    }

    /**
     * Only letters that have reached the VPA: approved by the club adviser, or decided by
     * the VPA before. One still with the adviser is not the VPA's to see yet.
     */
    private function letterOnly(ClubActivity $event): void
    {
        if (! ClubActivity::reachedVpa()->whereKey($event->id)->exists()) {
            abort(404);
        }
    }
}
