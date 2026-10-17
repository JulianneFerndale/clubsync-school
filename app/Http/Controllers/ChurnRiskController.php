<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubOfficer;
use App\Models\Semester;
use App\Services\ChurnRiskService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChurnRiskController extends Controller
{
    public function __construct(private ChurnRiskService $churn) {}

    public function index(Request $request): View
    {
        // Officers see only the clubs they hold a position in (per-club privilege,
        // never global). Members, advisers and the DSA never reach here — blocked by
        // the route middleware; the DSA follows clubs on dsa.club-performance instead.
        $clubIds = ClubOfficer::where('user_id', auth_user_id())->pluck('club_id');
        $clubs = Club::whereIn('id', $clubIds)->orderBy('name')->get(['id', 'name', 'acronym']);

        $selectedClub = null;
        $rows = collect();
        $summary = ['total' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];

        if ($clubs->isNotEmpty()) {
            $requestedId = $request->integer('club_id');
            $selectedClub = $requestedId ? $clubs->firstWhere('id', $requestedId) : $clubs->first();

            // A club_id outside the user's allowed set (e.g. an officer probing
            // another club via the URL) is forbidden, not silently ignored.
            if ($requestedId && ! $selectedClub) {
                abort(403);
            }

            $rows = $this->churn->forClub($selectedClub);
            $summary = $this->churn->summarize($rows);
        }

        return view('churn-risk.index', [
            'clubs'        => $clubs,
            'selectedClub' => $selectedClub,
            'rows'         => $rows,
            'summary'      => $summary,
            'semester'     => Semester::current()->first(),
        ]);
    }
}
