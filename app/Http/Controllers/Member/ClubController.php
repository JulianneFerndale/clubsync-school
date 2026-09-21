<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Jobs\SyncClubMemberGoogleCalendar;
use App\Models\Announcement;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\ClubOfficer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClubController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->trim();

        // Members only browse & join non-academic clubs. Academic clubs are
        // auto-enrolled from the student's course, so they are never self-registered.
        $query = Club::active()->nonAcademic();

        if ($search->isNotEmpty()) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower((string) $search).'%'])
                  ->orWhereRaw('LOWER(acronym) LIKE ?', ['%'.strtolower((string) $search).'%']);
            });
        }

        $clubs = $query->orderBy('name')->paginate(18)->withQueryString();

        $joinedIds = ClubMember::where('user_id', auth_user_id())
            ->pluck('status', 'club_id');

        return view('member.clubs.index', compact('clubs', 'joinedIds', 'search'));
    }

    public function show(Club $club): View
    {
        $club->load('college', 'adviserUser');

        $upcomingEvents = ClubActivity::where('club_id', $club->id)
            ->upcoming()
            ->take(3)
            ->get();

        $announcements = Announcement::where('club_id', $club->id)
            ->published()
            ->take(5)
            ->get();

        $memberCount = $club->members()->where('status', 'active')->count();

        $membership = ClubMember::where('club_id', $club->id)
            ->where('user_id', auth_user_id())
            ->first();

        // Officers of THIS club get management access and always see its content.
        $isOfficer = ClubOfficer::where('club_id', $club->id)
            ->where('user_id', auth_user_id())
            ->exists();

        // A club's posts/activities are visible to approved members and to its officers.
        $canViewContent = $isOfficer || ($membership && $membership->status === 'active');

        return view('member.clubs.show', compact(
            'club',
            'upcomingEvents',
            'announcements',
            'memberCount',
            'membership',
            'canViewContent',
        ) + [
            'canManage' => $isOfficer, // renders the club-management panel
            'readOnly'  => $isOfficer, // officers don't join/leave their own club
        ]);
    }

    public function join(Club $club): RedirectResponse
    {
        $existing = ClubMember::where('club_id', $club->id)
            ->where('user_id', auth_user_id())
            ->first();

        if ($existing) {
            return back()->with('info', 'You have already submitted a membership request for this club.');
        }

        ClubMember::create([
            'club_id'    => $club->id,
            'user_id'    => auth_user_id(),
            'status'     => 'pending',
            'joined_at'  => now(),
            'date_joined' => now(),
        ]);

        return back()->with('success', 'Your membership request has been submitted. You will be notified once it has been reviewed.');
    }

    public function leave(Club $club): RedirectResponse
    {
        ClubMember::where('club_id', $club->id)
            ->where('user_id', auth_user_id())
            ->delete();

        // Take the club's activities back off their Google Calendar.
        SyncClubMemberGoogleCalendar::dispatch(auth_user_id(), $club->id);

        return back()->with('success', 'You have left ' . $club->name . '.');
    }
}
