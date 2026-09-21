<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubNotification;
use App\Models\Semester;
use App\Models\SemesterNarrativeReport;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\NarrativeReportData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Draft one club's end-of-semester narrative report and hand it to the adviser.
 *
 * The draft covers every activity the club completed in the semester, written from
 * NarrativeReportData. It is held as pending_review: nothing reaches the DSA until
 * the adviser has reviewed it and submitted it (POLICY.md § AI & Automation).
 */
class GenerateSemesterNarrativeReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $clubId, private int $semesterId) {}

    public function handle(GeminiService $gemini): void
    {
        $club     = Club::with('adviserUser')->find($this->clubId);
        $semester = Semester::find($this->semesterId);

        if (! $club || ! $semester) {
            return;
        }

        // One report per club per semester, however often the sweep runs.
        if (SemesterNarrativeReport::where('club_id', $club->id)->where('semester_id', $semester->id)->exists()) {
            return;
        }

        $data = NarrativeReportData::semester($club, $semester);

        if ($data['totals']['activities'] === 0) {
            return;
        }

        $draft = $gemini->draftSemesterNarrative($data);

        $report = SemesterNarrativeReport::create([
            'club_id'       => $club->id,
            'semester_id'   => $semester->id,
            'activity_data' => $data,
            'draft_content' => $draft,
            'ai_available'  => $draft !== null,
            'status'        => 'pending_review',
        ]);

        AuditLog::record('ai.semester_report.generated', $report, [
            'club_id'      => $club->id,
            'semester_id'  => $semester->id,
            'activities'   => $data['totals']['activities'],
            'ai_available' => $draft !== null,
        ]);

        $status = $draft !== null
            ? 'has been drafted and is awaiting your review before it is submitted to the DSA.'
            : 'could not be drafted (AI Unavailable — Manual Draft Required). Please write the narrative before submitting it to the DSA.';

        if ($club->adviser_id) {
            ClubNotification::create([
                'recipient_id' => $club->adviser_id,
                'sender_type'  => 'ai_queue',
                'club_id'      => $club->id,
                'title'        => 'Semester narrative report ready for review',
                'body'         => "{$club->name}: the {$semester->label} narrative report, covering "
                    . "{$data['totals']['activities']} completed " . str('activity')->plural($data['totals']['activities'])
                    . ", {$status}",
                'action_url'   => route('adviser.semester-reports.show', $report),
            ]);

            return;
        }

        // No adviser to review it: hold it, and tell the DSA why it has not arrived.
        foreach (User::where('role', 'dsa')->get() as $dsa) {
            ClubNotification::create([
                'recipient_id' => $dsa->id,
                'sender_type'  => 'system',
                'club_id'      => $club->id,
                'title'        => 'Semester narrative report held: no adviser',
                'body'         => "{$club->name} has no adviser assigned, so its {$semester->label} narrative report "
                    . 'is being held until an adviser can review and submit it.',
                'action_url'   => route('dsa.clubs.show', $club),
            ]);
        }
    }
}
