<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubNotification;
use App\Models\SemesterNarrativeReport;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\NarrativeDocuments\DocumentBuilder;
use App\Services\NarrativeDocuments\ReportDocuments;
use App\Services\NarrativeReportData;
use App\Services\NarrativeTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * The adviser's review of end-of-semester narrative reports.
 *
 * This is the human gate POLICY.md requires for AI output: a report is only turned
 * into documents and submitted to the DSA when the adviser presses submit here.
 * Until then the adviser can edit every drafted section, have the AI redraft an
 * activity, and preview each document as Word or PDF. A report the DSA returns for
 * revision comes back here as a draft, with the DSA's remarks, to be submitted again.
 */
class SemesterReportController extends Controller
{
    public function __construct(private ReportDocuments $documents, private GeminiService $gemini) {}

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
        $report->load('club', 'semester', 'reviewer', 'returner');
        $this->authorizeReport($report);

        return view($report->isLegacy() ? 'adviser.semester-reports.show' : 'adviser.semester-reports.review', [
            'report'      => $report,
            'data'        => $report->activity_data,
            'warnings'    => $report->isSubmitted() ? [] : $report->reviewWarnings(),
            'documents'   => $report->isLegacy() ? [] : (new DocumentBuilder($report))->summary(),
            'aiAvailable' => $this->gemini->isReady(),
        ]);
    }

    /**
     * Have the AI draft one activity's sections again: one it could not draft the first
     * time, or one the adviser (or the DSA, returning the report) wants rewritten.
     *
     * The form is saved first, so edits made anywhere on the page survive, and the new
     * draft replaces only this activity's sections. It is a draft like any other: it
     * reaches the DSA only when the adviser submits (POLICY.md § AI & Automation). When
     * the AI cannot answer, nothing in the activity changes and the adviser writes it.
     */
    public function redraft(Request $request, SemesterNarrativeReport $report, string $activity): RedirectResponse
    {
        $report->load('club', 'semester');
        $this->authorizeReport($report);

        if ($report->isSubmitted()) {
            return back()->withErrors(['status' => 'This report has already been submitted to the DSA.']);
        }

        $activityId = (int) $activity;
        $index      = array_search($activityId, $report->activityIds(), true);

        abort_if($report->isLegacy() || $index === false, 404);

        $validated = $request->validate([
            'sections'     => ['nullable', 'array'],
            'sections.*'   => ['array'],
            'sections.*.*' => ['nullable', 'string', 'max:20000'],
        ]);

        $this->mergeSections($report, $validated['sections'] ?? []);

        $entry = $report->activity_data['activities'][$index];
        $back  = redirect()->to(route('adviser.semester-reports.show', $report) . "#activity-{$activityId}")
            ->with('open_activity', $activityId);

        $draft = $this->gemini->draftReportSections(
            $report->activity_data, $entry, $index + 1, NarrativeReportData::recordsText($activityId, $report),
        );

        if ($draft === null) {
            $report->save();

            return $back->withErrors(['status' => "AI Unavailable — \"{$entry['title']}\" could not be redrafted, so nothing in it was changed. "
                . 'Your other edits were saved. Write it yourself, or try again later.']);
        }

        // ai_sections keeps the AI's latest draft of each activity, untouched, beside the adviser's copy.
        $report->ai_sections = array_replace($report->ai_sections ?? [], [$activityId => $draft]);
        $report->sections    = array_replace($report->sections ?? [], [$activityId => $draft]);
        $report->syncAiAvailable();
        $report->save();

        AuditLog::record('ai.semester_report.section_redrafted', $report, [
            'club_id'     => $report->club_id,
            'activity_id' => $activityId,
            'returned'    => $report->isReturned(),
        ]);

        return $back->with('success', "The AI redrafted \"{$entry['title']}\". Review it before you submit the report.");
    }

    /**
     * Write one activity's sections from its records in the fixed template — the manual
     * path's head start, which needs no AI (POLICY.md § AI & Automation). For an activity
     * the AI never drafted, or one to start again from the latest records.
     *
     * Saved like a redraft: the page's edits first, then only this activity replaced. An
     * AI draft already on record stays as the first draft; otherwise the template takes
     * that place, so `ai:catch-up` may still have the AI draft it if it is left unchanged.
     */
    public function fillFromRecords(Request $request, SemesterNarrativeReport $report, string $activity): RedirectResponse
    {
        $report->load('club', 'semester');
        $this->authorizeReport($report);

        if ($report->isSubmitted()) {
            return back()->withErrors(['status' => 'This report has already been submitted to the DSA.']);
        }

        $activityId = (int) $activity;
        $index      = array_search($activityId, $report->activityIds(), true);

        abort_if($report->isLegacy() || $index === false, 404);

        $validated = $request->validate([
            'sections'     => ['nullable', 'array'],
            'sections.*'   => ['array'],
            'sections.*.*' => ['nullable', 'string', 'max:20000'],
        ]);

        $this->mergeSections($report, $validated['sections'] ?? []);

        $entry    = $report->activity_data['activities'][$index];
        $template = NarrativeTemplate::sections($entry, $report);

        $report->sections = array_replace($report->sections ?? [], [$activityId => $template]);

        if ($report->draftSource($activityId) !== 'ai') {
            $report->ai_sections = array_replace($report->ai_sections ?? [], [
                $activityId => $template + ['source' => NarrativeTemplate::SOURCE],
            ]);
        }

        $report->syncAiAvailable();
        $report->save();

        AuditLog::record('semester_report.section_templated', $report, [
            'club_id'     => $report->club_id,
            'activity_id' => $activityId,
        ]);

        return redirect()->to(route('adviser.semester-reports.show', $report) . "#activity-{$activityId}")
            ->with('open_activity', $activityId)
            ->with('success', "\"{$entry['title']}\" was filled in from its records. Check and complete it before you submit the report.");
    }

    /** Save the adviser's edits, or save them and submit the report to the DSA. */
    public function update(Request $request, SemesterNarrativeReport $report): RedirectResponse
    {
        $report->load('club', 'semester');
        $this->authorizeReport($report);

        if ($report->isSubmitted()) {
            return back()->withErrors(['status' => 'This report has already been submitted to the DSA.']);
        }

        if (! $report->isLegacy()) {
            return $this->updateSections($request, $report);
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

        $warnings     = $report->reviewWarnings();
        $resubmission = $report->isReturned();

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
            'resubmission'         => $resubmission,
        ]);

        $this->notifyDsa($report, $resubmission);

        return redirect()->route('adviser.semester-reports.show', $report)
            ->with('success', $resubmission ? 'Revised report submitted to the DSA.' : 'Report submitted to the DSA.');
    }

    /**
     * The five-document report: save the adviser's edits to each activity's sections,
     * or save them and submit. Submitting writes the Word files first, so a document
     * that fails to render leaves the report with the adviser instead of "submitted"
     * with nothing to download.
     */
    private function updateSections(Request $request, SemesterNarrativeReport $report): RedirectResponse
    {
        $validated = $request->validate([
            'sections'     => ['required', 'array'],
            'sections.*'   => ['array'],
            'sections.*.*' => ['nullable', 'string', 'max:20000'],
            'intent'       => ['required', 'in:save,submit'],
        ]);

        $this->mergeSections($report, $validated['sections']);

        if ($validated['intent'] === 'save') {
            $report->save();

            AuditLog::record('semester_report.saved', $report, [
                'club_id' => $report->club_id,
                'edited'  => $report->sectionsEdited(),
            ]);

            return redirect()->route('adviser.semester-reports.show', $report)
                ->with('success', 'Changes saved. The report has not been submitted yet.');
        }

        // The one hard requirement: no activity goes to the DSA without its introduction.
        foreach ($report->activity_data['activities'] as $activity) {
            if (trim($report->section($activity['id'], 'introduction')) === '') {
                $report->save();

                return redirect()->route('adviser.semester-reports.show', $report)
                    ->withErrors(['status' => "Write the introduction for \"{$activity['title']}\" before submitting. Your other changes were saved."]);
            }
        }

        $warnings     = $report->reviewWarnings();
        $resubmission = $report->isReturned();

        $report->fill([
            'status'       => 'submitted',
            'reviewed_by'  => auth_user_id(),
            'reviewed_at'  => now(),
            'submitted_at' => now(),
        ]);

        try {
            $this->documents->writeSubmitted($report);
        } catch (\Throwable $e) {
            report($e);
            Storage::disk('local')->deleteDirectory($report->documentDirectory());

            return back()->withErrors(['status' => 'The documents could not be generated, so the report was not submitted. Please try again.']);
        }

        $report->save();

        AuditLog::record('semester_report.submitted', $report, [
            'club_id'             => $report->club_id,
            'semester_id'         => $report->semester_id,
            'edited'              => $report->sectionsEdited(),
            'unresolved_warnings' => count($warnings),
            'resubmission'        => $resubmission,
        ]);

        $this->notifyDsa($report, $resubmission);

        return redirect()->route('adviser.semester-reports.show', $report)
            ->with('success', $resubmission ? 'Revised report submitted to the DSA.' : 'Report submitted to the DSA.');
    }

    /** Copy the submitted sections onto the report: only the activities it covers, only the sections it knows. */
    private function mergeSections(SemesterNarrativeReport $report, array $submitted): void
    {
        $sections = $report->sections ?? [];

        foreach ($report->activityIds() as $id) {
            foreach (array_keys(SemesterNarrativeReport::SECTIONS) as $key) {
                if (array_key_exists($key, $submitted[$id] ?? [])) {
                    $sections[$id][$key] = str_replace("\r\n", "\n", trim((string) $submitted[$id][$key]));
                }
            }
        }

        $report->sections = $sections;
    }

    private function notifyDsa(SemesterNarrativeReport $report, bool $resubmission = false): void
    {
        $activities = "({$report->activity_data['totals']['activities']} activities)";

        foreach (User::where('role', 'dsa')->get() as $dsa) {
            ClubNotification::create([
                'recipient_id' => $dsa->id,
                'sender_type'  => 'system',
                'club_id'      => $report->club_id,
                'title'        => $resubmission ? 'Revised semester narrative report submitted' : 'Semester narrative report submitted',
                'body'         => $resubmission
                    ? "{$report->club->name} revised its {$report->semester->label} narrative report {$activities} after it was "
                        . 'returned on ' . $report->returned_at->format('M j, Y') . ', and the club adviser submitted it again.'
                    : "{$report->club->name} submitted its {$report->semester->label} narrative report {$activities}, reviewed by the club adviser.",
                'action_url'   => route('dsa.semester-reports.index'),
            ]);
        }
    }

    /** The narrative report as one PDF: the stored file of a legacy report, all five documents otherwise. */
    public function downloadPdf(SemesterNarrativeReport $report)
    {
        $report->load('club', 'semester');
        $this->authorizeReport($report);

        if (! $report->isLegacy()) {
            return self::send($this->documents->complete($report));
        }

        if (! $report->isSubmitted() || ! $report->pdf_path) {
            abort(404);
        }

        return Storage::disk('local')->download($report->pdf_path, self::fileName($report));
    }

    /** One document as Word or PDF — a preview while under review, the approved file once submitted. */
    public function document(SemesterNarrativeReport $report, string $document, string $format)
    {
        $report->load('club', 'semester');
        $this->authorizeReport($report);
        abort_if($report->isLegacy() || ! ReportDocuments::exists($document, $format), 404);

        return self::send($this->documents->document($report, $document, $format));
    }

    /** All five Word files in one ZIP. */
    public function bundle(SemesterNarrativeReport $report)
    {
        $report->load('club', 'semester');
        $this->authorizeReport($report);
        abort_if($report->isLegacy(), 404);

        return self::send($this->documents->bundle($report));
    }

    /** @param  array{bytes: string, name: string, mime: string}  $file */
    public static function send(array $file): Response
    {
        return response($file['bytes'], 200, [
            'Content-Type'        => $file['mime'],
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $file['name'], Str::ascii($file['name'])),
            'Cache-Control'       => 'private, no-store',
        ]);
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
