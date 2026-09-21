<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Services\OfficerTurnover;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private OfficerTurnover $turnover) {}

    public function index(): View
    {
        $club = Club::where('adviser_id', auth_user_id())->first();

        $turnoverPending      = false;
        $academicYear         = $this->turnover->academicYear();
        $upcomingEvents       = collect();
        $pendingCount         = 0;
        $recentAnnouncements  = collect();
        $memberCount          = 0;

        if ($club) {
            $upcomingEvents      = ClubActivity::where('club_id', $club->id)->upcoming()->take(5)->get();
            $pendingCount        = ClubActivity::where('club_id', $club->id)
                ->where('approval_status', 'pending_approval')->count();
            $recentAnnouncements = Announcement::where('club_id', $club->id)
                ->orderByDesc('created_at')->take(5)->get();
            $memberCount         = ClubMember::where('club_id', $club->id)
                ->where('status', 'active')->count();
            $turnoverPending     = $this->turnover->isPending($club);
        }

        return view('adviser.dashboard', compact(
            'turnoverPending',
            'academicYear',
            'club',
            'upcomingEvents',
            'pendingCount',
            'recentAnnouncements',
            'memberCount',
        ));
    }
}
