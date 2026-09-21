<?php

namespace App\Http\Controllers\DSA;

use App\Http\Controllers\Adviser\SemesterReportController as AdviserSemesterReportController;
use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\SemesterNarrativeReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/** End-of-semester narrative reports the club advisers have submitted to the DSA. */
class SemesterReportController extends Controller
{
    public function index(Request $request): View
    {
        $semesters = Semester::orderByDesc('start_date')->get();

        $reports = SemesterNarrativeReport::submitted()
            ->with('club', 'semester', 'reviewer')
            ->when($request->filled('semester_id'), fn ($query) => $query->where('semester_id', $request->integer('semester_id')))
            ->orderByDesc('submitted_at')
            ->paginate(20)
            ->withQueryString();

        return view('dsa.semester-reports.index', compact('reports', 'semesters'));
    }

    public function downloadPdf(SemesterNarrativeReport $report)
    {
        // Drafts still under adviser review are not the DSA's to read.
        if (! $report->isSubmitted() || ! $report->pdf_path) {
            abort(404);
        }

        $report->load('club', 'semester');

        return Storage::disk('local')->download($report->pdf_path, AdviserSemesterReportController::fileName($report));
    }
}
