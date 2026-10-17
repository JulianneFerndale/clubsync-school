<?php

namespace App\Http\Controllers\DSA;

use App\Http\Controllers\Adviser\SemesterReportController as AdviserSemesterReportController;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use App\Models\Semester;
use App\Models\SemesterNarrativeReport;
use App\Services\NarrativeDocuments\DocumentBuilder;
use App\Services\NarrativeDocuments\ReportDocuments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * End-of-semester narrative reports the club advisers have submitted to the DSA: the
 * five documents, each as Word or PDF, the complete report as one PDF, or the Word
 * files together. Drafts still under adviser review are not the DSA's to read.
 *
 * The DSA may return a submitted report for revision, with remarks. It goes back to
 * the adviser as a draft, and comes back here when the adviser submits it again.
 */
class SemesterReportController extends Controller
{
    public function __construct(private ReportDocuments $documents) {}

    public function index(Request $request): View
    {
        $semesters = Semester::orderByDesc('start_date')->get();
        $inSemester = fn ($query) => $query->when($request->filled('semester_id'), fn ($q) => $q->where('semester_id', $request->integer('semester_id')));

        $reports = SemesterNarrativeReport::submitted()
            ->with('club', 'semester', 'reviewer')
            ->tap($inSemester)
            ->orderByDesc('submitted_at')
            ->paginate(20)
            ->withQueryString();

        // Sent back for revision and not resubmitted yet: what the DSA is waiting on.
        $returned = SemesterNarrativeReport::pendingReview()
            ->whereNotNull('returned_at')
            ->with('club', 'semester', 'returner')
            ->tap($inSemester)
            ->orderByDesc('returned_at')
            ->get();

        return view('dsa.semester-reports.index', [
            'reports'   => $reports,
            'returned'  => $returned,
            'semesters' => $semesters,
            'documents' => DocumentBuilder::DOCUMENTS,
        ]);
    }

    /**
     * Send a submitted report back to the club adviser to revise.
     *
     * It becomes a draft again: the adviser can edit every section and have the AI
     * redraft an activity, and the officers can attach photos and records to its
     * activities again, since nothing is frozen until it is resubmitted. The documents
     * the DSA downloaded before are replaced when it is.
     */
    public function returnForRevision(Request $request, SemesterNarrativeReport $report): RedirectResponse
    {
        $report->load('club', 'semester');

        if (! $report->isSubmitted()) {
            return back()->withErrors(['revision' => 'Only a submitted report can be returned for revision.']);
        }

        $data = $request->validate([
            'remarks' => ['required', 'string', 'max:2000'],
        ], [
            'remarks.required' => 'Tell the adviser what to revise before returning the report.',
        ]);

        if (! $report->club?->adviser_id) {
            return back()->withErrors(['revision' => "{$report->club?->name} has no adviser assigned, so nobody could revise this report. Assign an adviser to the club first."]);
        }

        $remarks = trim($data['remarks']);

        $report->update([
            'status'         => 'pending_review',
            'submitted_at'   => null,
            'returned_at'    => now(),
            'returned_by'    => auth_user_id(),
            'return_remarks' => $remarks,
        ]);

        AuditLog::record('semester_report.returned', $report, [
            'club_id'     => $report->club_id,
            'semester_id' => $report->semester_id,
        ]);

        $this->notifyClub($report, $remarks);

        return redirect()->route('dsa.semester-reports.index')
            ->with('success', "Returned {$report->club->name}'s {$report->semester->label} narrative report to the adviser for revision.");
    }

    /** The adviser revises the report; the officers may need to attach what the DSA found missing. */
    private function notifyClub(SemesterNarrativeReport $report, string $remarks): void
    {
        $club  = $report->club;
        $label = $report->semester->label;

        ClubNotification::create([
            'recipient_id' => $club->adviser_id,
            'sender_type'  => 'system',
            'club_id'      => $club->id,
            'title'        => 'Semester narrative report returned for revision',
            'body'         => "The DSA returned {$club->name}'s {$label} narrative report for revision. Remarks: {$remarks} "
                . 'Revise it and submit it to the DSA again.',
            'action_url'   => route('adviser.semester-reports.show', $report),
        ]);

        $officerIds = ClubOfficer::where('club_id', $club->id)->pluck('user_id')->unique()
            ->reject(fn ($id) => (int) $id === (int) $club->adviser_id);

        foreach ($officerIds as $officerId) {
            ClubNotification::create([
                'recipient_id' => $officerId,
                'sender_type'  => 'system',
                'club_id'      => $club->id,
                'title'        => 'Semester narrative report returned for revision',
                'body'         => "The DSA returned the {$label} narrative report to your adviser for revision. Remarks: {$remarks} "
                    . 'If photos or records are missing, attach them on the activity pages before the adviser resubmits it.',
                'action_url'   => route('officer.activities.index'),
            ]);
        }
    }

    /** The narrative report as one PDF: the stored file of a legacy report, all five documents otherwise. */
    public function downloadPdf(SemesterNarrativeReport $report)
    {
        $report = $this->submitted($report);

        if (! $report->isLegacy()) {
            return AdviserSemesterReportController::send($this->documents->complete($report));
        }

        abort_unless($report->pdf_path, 404);

        return Storage::disk('local')->download($report->pdf_path, AdviserSemesterReportController::fileName($report));
    }

    public function document(SemesterNarrativeReport $report, string $document, string $format)
    {
        $report = $this->submitted($report);
        abort_if($report->isLegacy() || ! ReportDocuments::exists($document, $format), 404);

        return AdviserSemesterReportController::send($this->documents->document($report, $document, $format));
    }

    public function bundle(SemesterNarrativeReport $report)
    {
        $report = $this->submitted($report);
        abort_if($report->isLegacy(), 404);

        return AdviserSemesterReportController::send($this->documents->bundle($report));
    }

    private function submitted(SemesterNarrativeReport $report): SemesterNarrativeReport
    {
        abort_unless($report->isSubmitted(), 404);

        return $report->load('club', 'semester');
    }
}
