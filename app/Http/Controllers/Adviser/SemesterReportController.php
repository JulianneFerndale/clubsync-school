<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubNotification;
use App\Models\SemesterNarrativeReport;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The adviser's review of end-of-semester narrative reports.
 *
 * This is the human gate POLICY.md requires for AI output: a report is only turned
 * into a document and submitted to the DSA when the adviser presses submit here.
 */
class SemesterReportController extends Controller
{
    private function authorizeReport(SemesterNarrativeReport $report): void
    {
        if (! $report->club?->adviser_id || (int) $report->club->adviser_id !== auth_user_id()) {
            abort(403);
        }
    }

    public function index(): View
    {
        $clubIds = Club::where('adviser_id', auth_user_id())->pluck('id');

        $pending = SemesterNarrativeReport::whereIn('club_id', $clubIds)
            ->pendingReview()
            ->with('club', 'semester')
            ->orderBy('created_at')
            ->get();

        $submitted = SemesterNarrativeReport::whereIn('club_id', $clubIds)
            ->submitted()
            ->with('club', 'semester')
            ->orderByDesc('submitted_at')
            ->take(20)
            ->get();

        return view('adviser.semester-reports.index', [
            'hasClub'   => $clubIds->isNotEmpty(),
            'pending'   => $pending,
            'submitted' => $submitted,
        ]);
    }

    public function show(SemesterNarrativeReport $report): View
    {
        $report->load('club', 'semester', 'reviewer');
        $this->authorizeReport($report);

        return view('adviser.semester-reports.show', [
            'report'   => $report,
            'data'     => $report->activity_data,
            'warnings' => $report->isSubmitted() ? [] : $report->reviewWarnings(),
        ]);
    }

    /** Save the adviser's edits, or save them and submit the report to the DSA. */
    public function update(Request $request, SemesterNarrativeReport $report): RedirectResponse
    {
        $report->load('club', 'semester');
        $this->authorizeReport($report);

        if ($report->isSubmitted()) {
            return back()->withErrors(['status' => 'This report has already been submitted to the DSA.']);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'min:20'],
            'intent'  => ['required', 'in:save,submit'],
        ], [
            'content.required' => 'The narrative cannot be empty.',
            'content.min'      => 'The narrative is too short to submit.',
        ]);

        $content = str_replace("\r\n", "\n", trim($validated['content']));

        $report->adviser_edited_content = $content !== trim((string) $report->draft_content) ? $content : null;

        if ($validated['intent'] === 'save') {
            $report->save();

            AuditLog::record('semester_report.saved', $report, [
                'club_id' => $report->club_id,
                'edited'  => $report->adviser_edited_content !== null,
            ]);

            return redirect()->route('adviser.semester-reports.show', $report)
                ->with('success', 'Changes saved. The report has not been submitted yet.');
        }

        $warnings = $report->reviewWarnings();

        $report->fill([
            'status'       => 'submitted',
            'reviewed_by'  => auth_user_id(),
            'reviewed_at'  => now(),
            'submitted_at' => now(),
        ]);

        // Render the document before committing the status, so a rendering failure
        // leaves the report pending rather than "submitted" with no file.
        $report->pdf_path = $this->storePdf($report);
        $report->save();

        AuditLog::record('semester_report.submitted', $report, [
            'club_id'              => $report->club_id,
            'semester_id'          => $report->semester_id,
            'edited'               => $report->adviser_edited_content !== null,
            'unresolved_warnings'  => count($warnings),
        ]);

        foreach (User::where('role', 'dsa')->get() as $dsa) {
            ClubNotification::create([
                'recipient_id' => $dsa->id,
                'sender_type'  => 'system',
                'club_id'      => $report->club_id,
                'title'        => 'Semester narrative report submitted',
                'body'         => "{$report->club->name} submitted its {$report->semester->label} narrative report "
                    . "({$report->activity_data['totals']['activities']} activities), reviewed by the club adviser.",
                'action_url'   => route('dsa.semester-reports.index'),
            ]);
        }

        return redirect()->route('adviser.semester-reports.show', $report)
            ->with('success', 'Report submitted to the DSA.');
    }

    public function downloadPdf(SemesterNarrativeReport $report)
    {
        $report->load('club');
        $this->authorizeReport($report);

        if (! $report->isSubmitted() || ! $report->pdf_path) {
            abort(404);
        }

        return Storage::disk('local')->download($report->pdf_path, self::fileName($report));
    }

    /** The download name both the adviser and the DSA get. */
    public static function fileName(SemesterNarrativeReport $report): string
    {
        return str("{$report->club->acronym} {$report->semester->label} Narrative Report")->slug() . '.pdf';
    }

    private function storePdf(SemesterNarrativeReport $report): string
    {
        $path = "reports/semester-narratives/{$report->id}/narrative-report.pdf";

        Pdf::loadView('reports.semester-narrative-pdf', [
            'report'     => $report,
            'data'       => $report->activity_data,
            'paragraphs' => preg_split('/\n\s*\n/', trim((string) $report->finalContent())),
            'reviewer'   => User::find(auth_user_id()),
        ])->save($path, 'local');

        return $path;
    }
}
