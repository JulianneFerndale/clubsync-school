<?php

namespace App\Http\Controllers\VPA;

use App\Http\Controllers\Controller;
use App\Models\ClubActivity;
use App\Models\User;
use Illuminate\View\View;

/** The Vice President for Administration's home: letters waiting, and the rooms booked ahead. */
class DashboardController extends Controller
{
    public function index(): View
    {
        $pendingCount = ClubActivity::where('approval_status', 'pending_vpa')->count();

        $pending = ClubActivity::where('approval_status', 'pending_vpa')
            ->with('club', 'creator')
            ->orderBy('date')
            ->orderBy('time_start')
            ->take(8)
            ->get();

        $approvedThisMonth = ClubActivity::where('vpa_decision', 'approved')
            ->where('vpa_reviewed_at', '>=', now()->startOfMonth())
            ->count();

        $returnedThisMonth = ClubActivity::where('vpa_decision', 'rejected')
            ->where('vpa_reviewed_at', '>=', now()->startOfMonth())
            ->count();

        // Rooms the VPA has approved that are still to be used, whether or not the
        // adviser has confirmed the activity yet.
        $booked = ClubActivity::where('vpa_decision', 'approved')
            ->whereIn('approval_status', ['pending_approval', 'approved'])
            ->where('status', 'scheduled')
            ->whereDate('date', '>=', today())
            ->with('club')
            ->orderBy('date')
            ->orderBy('time_start')
            ->take(8)
            ->get();

        $hasSignature = (bool) User::find(auth_user_id())?->signature_path;

        return view('vpa.dashboard', compact('pendingCount', 'pending', 'approvedThisMonth', 'returnedThisMonth', 'booked', 'hasSignature'));
    }
}
