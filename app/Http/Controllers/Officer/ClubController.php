<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\ClubOfficer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ClubController extends Controller
{
    /**
     * Browse non-academic clubs the officer can enroll in. Academic clubs are
     * course-mapped and auto-enrolled, so they are not joinable here.
     */
    public function index(): View
    {
        $clubs = Club::active()->nonAcademic()->orderBy('name')->paginate(18);

        $joinedIds = ClubMember::where('user_id', auth_user_id())->pluck('status', 'club_id');

        return view('officer.clubs.index', compact('clubs', 'joinedIds'));
    }

    /**
     * Read-only view of a club the officer is enrolled in (as a member or officer).
     * Reuses the member club page via a shared, layout-flexible Blade view. The
     * Add Activity / Draft Post controls appear only when the viewer is an officer
     * of THIS club.
     */
    public function show(Club $club): View
    {
        $userId = auth_user_id();

        $isOfficer = ClubOfficer::where('club_id', $club->id)->where('user_id', $userId)->exists();
        $isMember  = ClubMember::where('club_id', $club->id)->where('user_id', $userId)->exists();

        abort_unless($isOfficer || $isMember, 403);

        $club->load('adviserUser');

        return view('member.clubs.show', [
            'club'           => $club,
            'upcomingEvents' => ClubActivity::where('club_id', $club->id)->upcoming()->take(3)->get(),
            'announcements'  => Announcement::where('club_id', $club->id)->published()->take(5)->get(),
            'memberCount'    => $club->members()->where('status', 'active')->count(),
            'membership'     => null,
            'layout'         => 'layouts.app-officer',
            'dashboardRoute' => 'officer.dashboard',
            'readOnly'       => true,
            'canManage'      => $isOfficer, // show officer tools only for clubs they run
            'canViewContent' => true,       // enrolled (member or officer) — may see posts
        ]);
    }

    /**
     * Enroll the officer in a non-academic club (same pending-approval flow as members).
     */
    public function join(Club $club): RedirectResponse
    {
        // Mirror the same both-column test the index listing uses (scopeNonAcademic),
        // so a club that is non-academic by either the normalized `type` or the legacy
        // `club_type` column can actually be joined — not just listed as joinable.
        $isNonAcademic = $club->type === 'non_academic' || $club->club_type === 'Non-Academic';

        if (! $isNonAcademic) {
            return back()->withErrors(['club' => 'Only non-academic clubs can be joined here.']);
        }

        $existing = ClubMember::where('club_id', $club->id)->where('user_id', auth_user_id())->first();

        if ($existing) {
            return back()->with('info', 'You have already requested to join this club.');
        }

        ClubMember::create([
            'club_id'     => $club->id,
            'user_id'     => auth_user_id(),
            'status'      => 'pending',
            'joined_at'   => now(),
            'date_joined' => now(),
        ]);

        return back()->with('success', 'Your membership request has been submitted. Please wait for officer approval.');
    }
}
