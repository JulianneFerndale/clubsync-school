<?php

namespace App\Http\Controllers\DSA;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Semester;
use App\Services\ClubPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * How well each club has been doing this semester — the DSA's view in place of the
 * Churn Risk Engine, which is officer-only. Club-level figures only (POLICY.md
 * §Role-Based Access): nothing here scores or tags an individual member.
 */
class ClubPerformanceController extends Controller
{
    public function __construct(private ClubPerformanceService $performance) {}

    public function index(Request $request): View
    {
        [$semesters, $semester] = $this->semesters($request);

        $type     = in_array($request->query('type'), ['academic', 'non_academic'], true) ? $request->query('type') : null;
        $standing = array_key_exists((string) $request->query('standing'), ClubPerformanceService::STANDINGS)
            ? $request->query('standing')
            : null;

        $rows = $semester
            ? $this->performance->forClubs(
                Club::active()
                    ->when($type === 'academic', fn ($q) => $q->academic())
                    ->when($type === 'non_academic', fn ($q) => $q->nonAcademic())
                    ->orderBy('name')
                    ->get(),
                $semester,
            )
            : collect();

        // Counts come from every club in the type, so the chips stay put while filtering.
        $summary = $this->performance->summarize($rows);
        $shown   = $standing ? $rows->where('standing', $standing)->values() : $rows;

        return view('dsa.club-performance.index', [
            'semesters' => $semesters,
            'semester'  => $semester,
            'type'      => $type,
            'standing'  => $standing,
            'rows'      => $shown,
            'summary'   => $summary,
            'months'    => $semester ? $this->performance->months($semester) : [],
        ]);
    }

    public function show(Request $request, Club $club): View
    {
        [$semesters, $semester] = $this->semesters($request);

        abort_unless($semester, 404);

        return view('dsa.club-performance.show', [
            'semesters' => $semesters,
            'semester'  => $semester,
            'club'      => $club->load('adviserUser'),
            'row'       => $this->performance->forClub($club, $semester),
        ]);
    }

    /** @return array{0: Collection<int, Semester>, 1: ?Semester} */
    private function semesters(Request $request): array
    {
        $semesters = Semester::orderByDesc('start_date')->get();
        $semester  = $semesters->firstWhere('id', $request->integer('semester'))
            ?? $semesters->firstWhere('is_current', true)
            ?? $semesters->first();

        return [$semesters, $semester];
    }
}
