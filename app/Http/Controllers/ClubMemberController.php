<?php

namespace App\Http\Controllers;

use App\Jobs\NotifyAdviserOfMemberSubmission;
use App\Jobs\NotifyApplicantsOfSubmission;
use App\Jobs\NotifyClubOfMemberDecision;
use App\Jobs\SyncClubMemberGoogleCalendar;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubOfficer;
use App\Services\FirebaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ClubMemberController extends Controller
{
    public function __construct(private FirebaseService $firebase) {}

    private function currentClub(): ?Club
    {
        if (auth_role() === 'adviser') {
            return Club::where('adviser_id', auth_user_id())->first();
        }

        return ClubOfficer::where('user_id', auth_user_id())->with('club')->first()?->club;
    }

    public function index(): View
    {
        $club = $this->currentClub();

        $members = $club
            ? ClubMember::where('club_id', $club->id)
                ->with('user', 'submittedBy', 'approvedBy')
                ->orderByDesc('created_at')
                ->get()
            : collect();

        return view('clubs.members.index', compact('club', 'members'));
    }

    public function store(Request $request): RedirectResponse
    {
        $club = $this->currentClub();

        if (! $club) {
            return back()->withErrors(['club' => 'You are not associated with any club.']);
        }

        $validated = $request->validate([
            'member_ids'   => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:club_members,id'],
        ], [
            'member_ids.required' => 'Tick at least one applicant to submit to your adviser.',
            'member_ids.min'      => 'Tick at least one applicant to submit to your adviser.',
        ]);

        $members = ClubMember::where('club_id', $club->id)
            ->whereIn('id', $validated['member_ids'])
            ->where('registration_status', '!=', 'approved') // locked once approved
            ->get();

        // Applicants reaching the adviser now: never sent before, or sent back rejected.
        // A second press on someone already waiting must not tell them again.
        $forwarded = $members
            ->filter(fn (ClubMember $m) => $m->submitted_by === null || $m->registration_status === 'rejected')
            ->pluck('id')
            ->all();

        foreach ($members as $member) {
            $member->update([
                'submitted_by'        => auth_user_id(),
                'registration_status' => 'pending',
                'dsa_remarks'         => null,
                'approved_by'         => null,
                'approved_at'         => null,
            ]);
        }

        if ($members->isEmpty()) {
            return back()->with('info', 'No eligible members were selected for submission.');
        }

        // In the request, not the queue: the adviser has to hear about it even where
        // no queue worker runs (the Render image has none).
        NotifyAdviserOfMemberSubmission::dispatchSync($club->id, $members->count(), auth_user_id());

        if ($forwarded) {
            NotifyApplicantsOfSubmission::dispatchSync($forwarded);
        }

        return redirect()->route('clubs.members.index')
            ->with('success', $members->count() . ' member(s) submitted to your club adviser for review.');
    }

    /**
     * Adviser approves a pending member of their own club. Officers cannot approve.
     */
    public function approve(ClubMember $member): RedirectResponse
    {
        $this->authorizeAdviser($member);

        $member->update([
            'registration_status' => 'approved',
            'status'              => 'active',
            'approved_by'         => auth_user_id(),
            'approved_at'         => now(),
            'dsa_remarks'         => null,
        ]);

        $this->mirrorAndNotify($member, 'approved');

        return back()->with('success', 'Member approved.');
    }

    /**
     * Adviser rejects a pending member of their own club.
     */
    public function reject(Request $request, ClubMember $member): RedirectResponse
    {
        $this->authorizeAdviser($member);

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'min:5'],
        ], [
            'remarks.required' => 'Please provide a reason for rejection.',
        ]);

        $member->update([
            'registration_status' => 'rejected',
            'dsa_remarks'         => $validated['remarks'],
            'approved_by'         => auth_user_id(),
            'approved_at'         => null,
        ]);

        $this->mirrorAndNotify($member, 'rejected');

        return back()->with('success', 'Member rejected.');
    }

    /**
     * Only the adviser of the member's own club may approve/reject.
     */
    private function authorizeAdviser(ClubMember $member): void
    {
        abort_unless(
            auth_role() === 'adviser' && $member->club && (int) $member->club->adviser_id === auth_user_id(),
            403
        );
    }

    private function mirrorAndNotify(ClubMember $member, string $decision): void
    {
        try {
            $this->firebase->writeMemberStatus($member->club_id, $member->id, [
                'registration_status' => $member->registration_status,
                'decided_at'          => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Firebase member_status mirror failed for member #' . $member->id . ': ' . $e->getMessage());
        }

        // In the request, not the queue, for the same reason as the submission notice.
        NotifyClubOfMemberDecision::dispatchSync($member->id, $decision, auth_user_id());

        // An approved member gets the club's upcoming activities on their Google Calendar.
        SyncClubMemberGoogleCalendar::dispatch($member->user_id, $member->club_id);
    }
}
