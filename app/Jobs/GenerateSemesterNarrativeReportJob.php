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
use App\Services\NarrativeTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Draft one club's end-of-semester narrative report and hand it to the adviser.
 *
 * The report is the five DSA documents. Gemini drafts the written sections of each
 * completed activity (introduction, rationale, minutes for meetings, recommendations)
 * from NarrativeReportData and the records the officers attached; the documents
 * compile those sections with the activity, attendance, photo and finance records.
 * It is held as pending_review: nothing reaches the DSA until the adviser has
 * reviewed it and submitted it (POLICY.md § AI & Automation).
 */
class GenerateSemesterNarrativeReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** One Gemini request per activity, each allowed 90 seconds. */
    public int $timeout = 900;

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

        // Every activity gets an entry, so the review page and the documents always have
        // the same shape. One the AI cannot draft starts from its records in a fixed
        // template instead of blank (POLICY.md § AI & Automation), marked as such in
        // ai_sections so the page flags it and `ai:catch-up` can ask the AI again later.
        // During a Gemini outage every call returns at once, so this stays quick.
        $sections = [];
        $first    = [];
        $drafted  = 0;

        foreach ($data['activities'] as $i => $activity) {
            $records = NarrativeReportData::recordsText($activity['id']);
            $draft   = $gemini->draftReportSections($data, $activity, $i + 1, $records);

            if ($draft !== null) {
                $sections[$activity['id']] = $first[$activity['id']] = $draft;
                $drafted++;

                continue;
            }

            $sections[$activity['id']] = NarrativeTemplate::sections($activity);
            $first[$activity['id']]    = $sections[$activity['id']] + ['source' => NarrativeTemplate::SOURCE];
        }

        $total = count($data['activities']);

        $report = SemesterNarrativeReport::create([
            'club_id'       => $club->id,
            'semester_id'   => $semester->id,
            'activity_data' => $data,
            'ai_sections'   => $first,
            'sections'      => $sections,
            'ai_available'  => $drafted === $total,
            'status'        => 'pending_review',
        ]);

        AuditLog::record('ai.semester_report.generated', $report, [
            'club_id'      => $club->id,
            'semester_id'  => $semester->id,
            'activities'   => $total,
            'drafted'      => $drafted,
            'ai_available' => $drafted === $total,
        ]);

        $template = 'were filled in from their records as a starting template. Check and complete them before submitting '
            . 'it to the DSA; ClubSync asks the AI again later for any you leave unchanged.';

        $status = match (true) {
            $drafted === $total => 'has been drafted and is awaiting your review before it is submitted to the DSA.',
            $drafted === 0      => "could not be drafted by the AI (AI Unavailable — Manual Draft Required). Its activities {$template}",
            default             => "was only partly drafted by the AI: {$drafted} of {$total} activities (AI Unavailable — Manual Draft Required for the rest). The rest {$template}",
        };

        if ($club->adviser_id) {
            ClubNotification::create([
                'recipient_id' => $club->adviser_id,
                'sender_type'  => 'ai_queue',
                'club_id'      => $club->id,
                'title'        => 'Semester narrative report ready for review',
                'body'         => "{$club->name}: the {$semester->label} narrative report (accomplishment, minutes, financial, "
                    . 'property inventory and communications documents), covering '
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
