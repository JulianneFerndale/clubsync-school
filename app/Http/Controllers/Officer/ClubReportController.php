<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use App\Models\ClubReportSubmission;
use App\Models\ResourceDocument;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The club's semester-end reports to the DSA. Every officer sees what is due, can
 * fetch the templates and read the DSA's remarks — the other officers prepare their
 * parts — but only the club's president submits a report.
 */
class ClubReportController extends Controller
{
    /** Office documents or PDFs, as the templates are Word files. */
    private const MIMES = 'pdf,doc,docx,xls,xlsx';

    public function index(): View
    {
        $officer  = $this->officerRecord();
        $club     = $officer?->club;
        $semester = Semester::current()->first();

        $submissions = $club && $semester
            ? ClubReportSubmission::where('club_id', $club->id)
                ->where('semester_id', $semester->id)
                ->with('submitter', 'reviewer')
                ->get()
                ->keyBy('report_type')
            : collect();

        return view('officer.club-reports.index', [
            'club'        => $club,
            'semester'    => $semester,
            'isPresident' => $officer?->position === 'president',
            'types'       => ClubReportSubmission::TYPES,
            'submissions' => $submissions,
            'templates'   => ResourceDocument::whereNotNull('report_type')->get()->keyBy('report_type'),
        ]);
    }

    public function submit(Request $request, string $type): RedirectResponse
    {
        abort_unless(array_key_exists($type, ClubReportSubmission::TYPES), 404);

        $officer = $this->officerRecord();

        // POLICY: officer privileges are per club — it is the president *of this club*.
        abort_unless($officer && $officer->position === 'president', 403, 'Only the club president can submit reports to the DSA.');

        $club     = $officer->club;
        $semester = Semester::current()->first();

        if (! $semester) {
            return back()->withErrors(['report' => 'No semester is open, so there is nothing to submit a report for. Contact the DSA.']);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:' . self::MIMES, 'max:20480'],
        ], [
            'file.required' => 'Choose the completed report to upload.',
            'file.mimes'    => 'Upload the report as a PDF, Word or Excel file.',
            'file.max'      => 'The file may not be larger than 20 MB.',
        ]);

        $existing = ClubReportSubmission::where('club_id', $club->id)
            ->where('semester_id', $semester->id)
            ->where('report_type', $type)
            ->first();

        if ($existing?->isLocked()) {
            return back()->withErrors(['report' => 'The DSA has already accepted this report, so it can no longer be replaced.']);
        }

        $file    = $request->file('file');
        $oldPath = $existing?->file_path;
        $wasReturned = $existing?->status === 'returned';

        $submission = $existing ?? new ClubReportSubmission([
            'club_id'     => $club->id,
            'semester_id' => $semester->id,
            'report_type' => $type,
        ]);

        $submission->fill([
            'file_path'      => $file->store("club-reports/{$club->id}/{$semester->id}", 'local'),
            'file_name'      => $file->getClientOriginalName(),
            'file_size'      => $file->getSize(),
            'status'         => 'submitted',
            'submitted_by'   => auth_user_id(),
            'submitted_at'   => now(),
            // A resubmission starts a fresh review.
            'reviewed_by'    => null,
            'reviewed_at'    => null,
            'review_remarks' => null,
        ])->save();

        if ($oldPath && $oldPath !== $submission->file_path) {
            Storage::disk('local')->delete($oldPath);
        }

        AuditLog::record($existing ? 'club_report.resubmitted' : 'club_report.submitted', $submission, [
            'club_id'     => $club->id,
            'semester_id' => $semester->id,
            'report_type' => $type,
        ]);

        $this->notifyDsa($club, $submission, $existing !== null, $wasReturned);

        return redirect()->route('officer.club-reports.index')
            ->with('success', $submission->type_label . ' was submitted to the DSA for review.');
    }

    public function download(ClubReportSubmission $submission): Response
    {
        $officer = $this->officerRecord();

        // Cross-club access is blocked: officers see their own club's reports only.
        abort_unless($officer && $officer->club_id === $submission->club_id, 404);
        abort_unless(Storage::disk('local')->exists($submission->file_path), 404);

        return Storage::disk('local')->download($submission->file_path, $submission->downloadName());
    }

    /**
     * The officer seat this page acts for. Someone who is president of one club and
     * holds another post elsewhere acts for the club they preside over.
     */
    private function officerRecord(): ?ClubOfficer
    {
        return ClubOfficer::where('user_id', auth_user_id())
            ->with('club')
            ->orderByRaw("CASE WHEN position = 'president' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();
    }

    private function notifyDsa(Club $club, ClubReportSubmission $submission, bool $replaced, bool $afterReturn): void
    {
        $what = $afterReturn ? 'resubmitted' : ($replaced ? 'replaced' : 'submitted');

        foreach (User::where('role', 'dsa')->where('is_suspended', false)->get() as $dsa) {
            ClubNotification::create([
                'recipient_id' => $dsa->id,
                'sender_type'  => 'system',
                'club_id'      => $club->id,
                'title'        => "Club report {$what}",
                'body'         => "{$club->name} {$what} its {$submission->type_label} for {$submission->semester?->label}.",
                'action_url'   => route('dsa.club-reports.index'),
            ]);
        }
    }
}
