<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Services\AcleMonitorService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private AcleMonitorService $acleMonitor) {}

    public function index(Request $request): View
    {
        $totalClubs      = Club::count();
        $totalActivities = ClubActivity::count();

        return view('admin.dashboard', array_merge(
            compact('totalClubs', 'totalActivities'),
            $this->acleMonitor->build($request),
        ));
    }
}
