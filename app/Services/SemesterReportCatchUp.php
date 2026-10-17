<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ClubNotification;
use App\Models\SemesterNarrativeReport;
use Illuminate\Support\Facades\DB;

/**
 * Once the AI is back, draft the report activities that had to start from a template
 * while it was down (POLICY.md § AI & Automation: queued and generated later).
 *
 * Only an activity the adviser has left exactly as the template wrote it is redrafted —
 * anything they edited is theirs and stays. The new draft is a draft like the first:
 * the adviser is told, and nothing reaches the DSA until they submit.
 */
class SemesterReportCatchUp
{
    public function __construct(private GeminiService $gemini) {}

    /**
     * @param  int  $limit  The most activities to send to the AI in one run.
     * @return array{drafted: int, reports: int}
     */
    public function run(int $limit): array
    {
        $tally = ['drafted' => 0, 'reports' => 0];

        if (! $this->gemini->isReady()) {
            return $tally;
        }

        $reports = SemesterNarrativeReport::pendingReview()
            ->whereNotNull('sections')
            ->with('club', 'semester')
            ->orderBy('created_at')
            ->get()
            ->filter(fn (SemesterNarrativeReport $report) => $this->waiting($report) !== []);

        foreach ($reports as $report) {
            $drafted = [];
            $stop    = false;

            foreach ($report->activity_data['activities'] as $index => $entry) {
                if (! in_array((int) $entry['id'], $this->waiting($report), true)) {
                    continue;
                }

                if ($tally['drafted'] >= $limit || ! $this->gemini->isReady()) {
                    $stop = true;
                    break;
                }

                $id    = (int) $entry['id'];
                $draft = $this->gemini->draftReportSections(
                    $report->activity_data, $entry, $index + 1, NarrativeReportData::recordsText($id, $report),
                );

                if ($draft !== null && $this->apply($report->id, $id, $draft)) {
                    $drafted[] = $entry['title'];
                    $tally['drafted']++;
                }
            }

            if ($drafted !== []) {
                $tally['reports']++;
                $this->notifyAdviser($report->fresh(['club', 'semester']), $drafted);
            }

            if ($stop) {
                break;
            }
        }

        return $tally;
    }

    /** @return list<int> The activities of a report still on their untouched template draft. */
    private function waiting(SemesterNarrativeReport $report): array
    {
        return array_values(array_filter(
            $report->activityIds(),
            fn (int $id) => $report->draftSource($id) === NarrativeTemplate::SOURCE && $report->sectionsUntouched($id),
        ));
    }

    /**
     * Put the draft in, unless the report moved on while the AI was answering: submitted,
     * or the adviser edited that activity. Locked and re-read, so a save the adviser made
     * in the meantime is never overwritten by the copy this run started from.
     */
    private function apply(int $reportId, int $activityId, array $draft): bool
    {
        return DB::transaction(function () use ($reportId, $activityId, $draft) {
            $report = SemesterNarrativeReport::whereKey($reportId)->lockForUpdate()->first();

            if (! $report || $report->isSubmitted()
                || $report->draftSource($activityId) !== NarrativeTemplate::SOURCE
                || ! $report->sectionsUntouched($activityId)) {
                return false;
            }

            $report->ai_sections = array_replace($report->ai_sections ?? [], [$activityId => $draft]);
            $report->sections    = array_replace($report->sections ?? [], [$activityId => $draft]);
            $report->syncAiAvailable();
            $report->save();

            AuditLog::record('ai.semester_report.section_redrafted', $report, [
                'club_id'     => $report->club_id,
                'activity_id' => $activityId,
                'returned'    => $report->isReturned(),
                'automatic'   => true,
            ]);

            return true;
        });
    }

    /** @param  list<string>  $titles */
    private function notifyAdviser(SemesterNarrativeReport $report, array $titles): void
    {
        if (! $report->club?->adviser_id) {
            return;
        }

        $count = count($titles);

        ClubNotification::create([
            'recipient_id' => $report->club->adviser_id,
            'sender_type'  => 'ai_queue',
            'club_id'      => $report->club_id,
            'title'        => 'AI drafts added to your semester report',
            'body'         => "{$report->club->name}: the AI is available again and drafted {$count} "
                . str('activity')->plural($count) . " of the {$report->semester->label} narrative report that had "
                . 'been filled in from a template: ' . implode(', ', $titles) . '. Review '
                . ($count === 1 ? 'it' : 'them') . ' before you submit the report.',
            'action_url'   => route('adviser.semester-reports.show', $report),
        ]);
    }
}
