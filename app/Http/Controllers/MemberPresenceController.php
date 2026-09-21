<?php

namespace App\Http\Controllers;

use App\Jobs\NotifyDsaOfPresenceSubmission;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubOfficer;
use App\Models\MemberSemesterStatus;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberPresenceController extends Controller
{
    private function currentClub(): ?Club
    {
        if (auth_role() === 'adviser') {
            return Club::where('adviser_id', auth_user_id())->first();
        }

        return ClubOfficer::where('user_id', auth_user_id())->with('club')->first()?->club;
    }

    public function index(): View
    {
        $club    = $this->currentClub();
        $semester = Semester::current()->first();

        $members = collect();

        if ($club) {
            $members = ClubMember::where('club_id', $club->id)
                ->where('registration_status', 'approved')
                ->with('user')
                ->get()
                ->map(function (ClubMember $member) {
                    $member->current_status = $member->currentSemesterStatus();

                    return $member;
                });
        }

        return view('clubs.members.presence', compact('club', 'semester', 'members'));
    }

    public function update(Request $request, ClubMember $member): RedirectResponse
    {
        $club = $this->currentClub();

        if (! $club || $member->club_id !== $club->id) {
            abort(403);
        }

        $semester = Semester::current()->first();

        if (! $semester) {
            return back()->withErrors(['semester' => 'There is no active semester configured.']);
        }

        $validated = $request->validate([
            'semester_status' => ['required', 'in:active,inactive,dropped'],
        ]);

        MemberSemesterStatus::create([
            'club_member_id'  => $member->id,
            'semester_id'     => $semester->id,
            'semester_status' => $validated['semester_status'],
            'updated_by'      => auth_user_id(),
        ]);

        return back()->with('success', 'Presence status updated for ' . ($member->user?->name ?? 'member') . '.');
    }

    public function notifyDsa(): RedirectResponse
    {
        $club     = $this->currentClub();
        $semester = Semester::current()->first();

        if (! $club || ! $semester) {
            return back()->withErrors(['semester' => 'There is no active club or semester to submit.']);
        }

        NotifyDsaOfPresenceSubmission::dispatch($club->id, $semester->id, auth_user_id());

        return back()->with('success', 'The DSA has been notified that your semestral presence update is complete.');
    }
}
