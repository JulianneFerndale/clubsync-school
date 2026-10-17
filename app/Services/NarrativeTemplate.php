<?php

namespace App\Services;

use App\Models\ActivityPhoto;
use App\Models\ClubActivity;
use App\Models\SemesterNarrativeReport;

/**
 * An activity's report sections written from its records alone, for when the AI cannot
 * draft them (POLICY.md § AI & Automation).
 *
 * The AI turns the activity records into prose; this puts the same records into fixed
 * sentences. It quotes only what was recorded — the details the officer entered, the
 * attendance as checked in, what the officers attached — so it passes the same review
 * checks an AI draft does, and the adviser starts from a complete, accurate draft
 * instead of a blank page. It reads like a form because it is one: the adviser is
 * expected to polish it, and the AI may still redraft it once it is back.
 *
 * Like the AI's prompt, it never turns a plan into an outcome: the description and
 * purpose were written before the activity, so they are quoted as the proposal.
 */
class NarrativeTemplate
{
    /** Marks a first draft as the template's in ai_sections, where untouched first drafts are kept. */
    public const SOURCE = 'template';

    /** Below this share of the expected participants, the recommendations ask for better turnout. */
    private const LOW_TURNOUT = 0.75;

    /**
     * @param  array  $activity  One entry of NarrativeReportData::semester()['activities']
     * @return array{introduction: string, rationale: string, minutes: string, recommendations: string}
     */
    public static function sections(array $activity, ?SemesterNarrativeReport $report = null): array
    {
        return [
            'introduction'    => self::introduction($activity),
            'rationale'       => self::rationale($activity),
            'minutes'         => ! empty($activity['is_meeting']) ? self::minutes($activity) : '',
            'recommendations' => self::recommendations($activity, self::attachments((int) $activity['id'], $report)),
        ];
    }

    private static function introduction(array $a): string
    {
        $opening = "\"{$a['title']}\" ({$a['type']}) was held on {$a['date']}, from {$a['time']}, at {$a['venue']}.";

        if (trim((string) $a['description']) !== '') {
            $opening .= " The officers' proposal described it as follows: " . self::sentence($a['description']);
        }

        $paragraphs = [$opening . ' ' . self::attendance($a)];

        if (! empty($a['post_report'])) {
            $paragraphs[] = "The officers' post-activity report states: " . self::sentence($a['post_report']);
        }

        return implode("\n\n", $paragraphs);
    }

    /** Both recorded figures, always: the review check looks for the attended and the expected number. */
    private static function attendance(array $a): string
    {
        if (! $a['attendance_recorded']) {
            return 'Attendance was not recorded in ClubSync for this activity.';
        }

        if ((int) $a['expected'] < 1) {
            return "{$a['attended']} attended, as recorded in ClubSync; the expected number of participants was recorded as {$a['expected']}.";
        }

        return "Of the {$a['expected']} expected participants, {$a['attended']} attended"
            . ($a['turnout'] !== null ? ", a turnout of {$a['turnout']}%" : '')
            . ', as recorded in ClubSync.';
    }

    private static function rationale(array $a): string
    {
        return trim((string) $a['purpose']) !== ''
            ? 'According to its proposal, the activity was organised for the following purpose: ' . self::sentence($a['purpose'])
            : 'No purpose or objectives were recorded for this activity.';
    }

    /** The labelled lines the AI is asked for, filled in from the records. */
    private static function minutes(array $a): string
    {
        $agenda = trim((string) ($a['description'] ?: $a['purpose']));

        $attendance = $a['attendance_recorded']
            ? "{$a['attended']} of {$a['expected']} expected participants"
            : 'Not recorded in ClubSync.';

        return implode("\n", [
            "Date and time: {$a['date']}, {$a['time']}",
            "Venue: {$a['venue']}",
            "Attendance: {$attendance}",
            'Agenda: ' . ($agenda !== '' ? self::sentence($agenda) : 'Not recorded in ClubSync.'),
            'Proceedings: ' . (! empty($a['post_report']) ? self::sentence($a['post_report']) : 'Not recorded in ClubSync. See the attached minutes.'),
        ]);
    }

    /**
     * Two or three recommendations, each one a gap the records show — turnout against
     * the expected participants, documentation with nothing attached, a budget without
     * its receipts. Figures are quoted, never calculated, so none of them is new.
     */
    private static function recommendations(array $a, array $attached): string
    {
        $lines = [];

        if (! $a['attendance_recorded']) {
            $lines[] = 'Record attendance in ClubSync at every activity so that its turnout can be reported.';
        } elseif ((int) $a['expected'] > 0 && $a['attended'] < $a['expected'] * self::LOW_TURNOUT) {
            $lines[] = "Improve turnout: {$a['attended']} of the {$a['expected']} expected participants attended. "
                . 'Announce the activity earlier and remind members nearer the date.';
        } elseif ((int) $a['expected'] > 0) {
            $lines[] = "Keep the same scheduling and promotion: {$a['attended']} of the {$a['expected']} expected participants attended.";
        }

        if ($attached['documentation'] === 0) {
            $lines[] = 'Attach documentation photos of the activity to the report.';
        }

        if (! empty($a['is_meeting']) && $attached['minutes'] === 0 && empty($a['post_report'])) {
            $lines[] = 'Attach the written minutes of the meeting, or record its proceedings in the post-activity report.';
        }

        if ($attached['budget'] !== null) {
            $budget = 'PHP ' . number_format($attached['budget'], 2);

            if ($attached['receipts'] === 0) {
                $lines[] = "Attach the receipts for the {$budget} budget released for this activity.";
            } elseif ($attached['receipt_total'] < $attached['budget']) {
                $lines[] = 'Liquidate the budget in full: the receipts attached total PHP '
                    . number_format($attached['receipt_total'], 2) . " against the {$budget} released.";
            }
        }

        if ($lines === []) {
            $lines[] = 'Continue recording attendance, photos and receipts in ClubSync as this activity did.';
        }

        return implode("\n", array_map(fn (string $line) => "- {$line}", array_slice($lines, 0, 3)));
    }

    /**
     * What the officers attached, counted the way NarrativeReportData::recordsText()
     * counts it for the AI, so both drafts work from the same facts.
     *
     * @return array{documentation: int, minutes: int, receipts: int, receipt_total: float, budget: float|null}
     */
    private static function attachments(int $activityId, ?SemesterNarrativeReport $report): array
    {
        $photos = ActivityPhoto::where('club_activity_id', $activityId)
            ->when($report?->submitted_at, fn ($q) => $q->where('created_at', '<=', $report->submitted_at))
            ->get(['category', 'amount']);

        $budget = ClubActivity::whereKey($activityId)->value('budget_amount');

        return [
            'documentation' => $photos->where('category', 'documentation')->count(),
            'minutes'       => $photos->where('category', 'minutes')->count(),
            'receipts'      => $photos->where('category', 'receipt')->count(),
            'receipt_total' => round((float) $photos->where('category', 'receipt')->sum('amount'), 2),
            'budget'        => $budget !== null ? (float) $budget : null,
        ];
    }

    /** Officer-written text as a sentence: trimmed, line breaks folded, a full stop if it lacks one. */
    private static function sentence(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $text));

        return preg_match('/[.!?]["\')]?$/', $text) ? $text : "{$text}.";
    }
}
