<?php

namespace App\Http\Controllers\DSA;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubMember;
use Illuminate\View\View;

/**
 * DSA member view is read-only oversight. Approving/rejecting members is the
 * club adviser's responsibility (see App\Http\Controllers\ClubMemberController).
 */
class ClubMemberController extends Controller
{
    public function index(Club $club): View
    {
        $members = ClubMember::where('club_id', $club->id)
            ->with('user', 'submittedBy', 'approvedBy')
            ->orderByDesc('created_at')
            ->get();

        return view('dsa.members.review', compact('club', 'members'));
    }
}
