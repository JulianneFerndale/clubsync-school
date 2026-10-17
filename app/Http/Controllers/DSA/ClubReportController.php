<?php

namespace App\Http\Controllers\DSA;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use App\Models\ClubReportSubmission;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The DSA's review of the semester-end reports club presidents submit. A report is
 * accepted, which makes it final, or returned with remarks for the club to revise.
 */
class ClubReportController extends Controller
{
    public function index(Request $request): View
    {
        $semesters = Semester::orderByDesc('start_date')->get();
        $semester  = $semesters->firstWhere('id', $request->integer('semester'))
            ?? $semesters->firstWhere('is_current', true)
            ?? $semesters->first();

        $status = array_key_exists((string) $request->query('status'), ClubReportSubmission::STATUSES)
            ? $request->query('status')
            : null;

        $submissions = $semester
            ? ClubReportSubmission::where('semester_id', $semester->id)
                ->when($status, fn ($q) => $q->where('status', $status))
                ->with('club', 'submitter', 'reviewer')
                // What is waiting on the DSA first, then the oldest submission first.
                ->orderByRaw("CASE WHEN status = 'submitted' THEN 0 ELSE 1 END")
                ->orderBy('submitted_at')
                ->get()
            : collect();

        $counts = $semester
            ? ClubReportSubmission::where('semester_id', $semester->id)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
            : collect();

        // Clubs that have not filed every report yet this semester.
        $typeCount   = count(ClubReportSubmission::TYPES);
        $incomplete  = $semester
            ? Club::active()
                ->withCount(['reportSubmissions as filed_count' => fn ($q) => $q->where('semester_id', $semester->id)])
                ->orderBy('name')
                ->get()
                ->filter(fn (Club $club) => $club->filed_count < $typeCount)
                ->values()
            : collect();

        return view('dsa.club-reports.index', compact('semesters', 'semester', 'status', 'submissions', 'counts', 'incomplete', 'typeCount'));
    }

    public function download(ClubReportSubmission $submission): Response
    {
        abort_unless(Storage::disk('local')->exists($submission->file_path), 404);

        return Storage::disk('local')->download($submission->file_path, $submission->downloadName());
    }

    public function accept(ClubReportSubmission $submission): RedirectResponse
    {
        if ($submission->status !== 'submitted') {
            return back()->withErrors(['review' => 'Only a report awaiting review can be accepted.']);
        }

        $submission->update([
            'status'         => 'accepted',
            'reviewed_by'    => auth_user_id(),
            'reviewed_at'    => now(),
            'review_remarks' => null,
        ]);

        AuditLog::record('club_report.accepted', $submission, [
            'club_id'     => $submission->club_id,
            'report_type' => $submission->report_type,
        ]);

        $this->notifyClub($submission, 'Report accepted by the DSA',
            "The DSA accepted your {$submission->type_label} for {$submission->semester?->label}.");

        return back()->with('success', "Accepted {$submission->club?->name}'s {$submission->type_label}.");
    }

    public function returnForRevision(Request $request, ClubReportSubmission $submission): RedirectResponse
    {
        if ($submission->status !== 'submitted') {
            return back()->withErrors(['review' => 'Only a report awaiting review can be returned.']);
        }

        $data = $request->validate([
            'remarks' => ['required', 'string', 'max:2000'],
        ], [
            'remarks.required' => 'Tell the club what to revise before returning the report.',
        ]);

        $submission->update([
            'status'         => 'returned',
            'reviewed_by'    => auth_user_id(),
            'reviewed_at'    => now(),
            'review_remarks' => $data['remarks'],
        ]);

        AuditLog::record('club_report.returned', $submission, [
            'club_id'     => $submission->club_id,
            'report_type' => $submission->report_type,
        ]);

        $this->notifyClub($submission, 'Report returned for revision',
            "The DSA returned your {$submission->type_label} for {$submission->semester?->label}. Remarks: {$data['remarks']}");

        return back()->with('success', "Returned {$submission->club?->name}'s {$submission->type_label} for revision.");
    }

    /** Every officer of the club hears the outcome — they prepared the parts of it. */
    private function notifyClub(ClubReportSubmission $submission, string $title, string $body): void
    {
        $officerIds = ClubOfficer::where('club_id', $submission->club_id)->pluck('user_id')->unique();

        foreach ($officerIds as $userId) {
            ClubNotification::create([
                'recipient_id' => $userId,
                'sender_type'  => 'system',
                'club_id'      => $submission->club_id,
                'title'        => $title,
                'body'         => $body,
                'action_url'   => route('officer.club-reports.index'),
            ]);
        }
    }
}
