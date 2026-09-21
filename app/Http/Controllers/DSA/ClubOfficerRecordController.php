<?php

namespace App\Http\Controllers\DSA;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubOfficerRecord;
use Illuminate\View\View;

class ClubOfficerRecordController extends Controller
{
    public function index(Club $club): View
    {
        $records = ClubOfficerRecord::where('club_id', $club->id)
            ->orderByDesc('academic_year')
            ->orderBy('semester')
            ->orderBy('position')
            ->get();

        return view('dsa.clubs.officers.index', compact('club', 'records'));
    }
}
