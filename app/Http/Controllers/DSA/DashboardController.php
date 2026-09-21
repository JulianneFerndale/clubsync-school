<?php

namespace App\Http\Controllers\DSA;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Services\AcleMonitorService;
use App\Services\OfficerTurnover;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private AcleMonitorService $acleMonitor,
        private OfficerTurnover $turnover,
    ) {}

    public function index(Request $request): View
    {
        $totalClubs        = Club::count();
        $academicClubs     = Club::academic()->count();
        $nonAcademicClubs  = Club::nonAcademic()->count();
        $totalActivities   = ClubActivity::count();

        // Clubs still running last school year's officers. Empty for most of the
        // year; the list appears when the year turns over and shrinks as clubs act.
        $turnoverPending = $this->turnover->pendingClubs();
        $academicYear    = $this->turnover->academicYear();

        return view('dsa.dashboard', array_merge(
            compact('totalClubs', 'academicClubs', 'nonAcademicClubs', 'totalActivities',
                'turnoverPending', 'academicYear'),
            $this->acleMonitor->build($request),
        ));
    }
}
