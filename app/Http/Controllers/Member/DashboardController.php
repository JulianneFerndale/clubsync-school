<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Club;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $userId = auth_user_id();

        // One query for both lists (each list was its own database round trip).
        $myClubs = Club::active()
            ->whereHas('members', fn ($q) => $q->where('user_id', $userId))
            ->get();

        $academicClubs    = $myClubs->filter(fn (Club $club) => $club->isAcademicType())->values();
        $nonAcademicClubs = $myClubs->filter(fn (Club $club) => $club->isNonAcademicType())->values();

        // Clubs this user is an officer of — shown with management access. Officers
        // navigate as members; their admin tools live inside their own club page.
        $managedClubs = Club::active()
            ->whereHas('officers', fn ($q) => $q->where('user_id', $userId))
            ->get();

        return view('member.dashboard', compact('academicClubs', 'nonAcademicClubs', 'managedClubs'));
    }
}
