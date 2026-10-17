<?php

namespace App\Services;

use App\Models\ActivityPhoto;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\Fee;
use App\Models\Semester;
use App\Models\SemesterNarrativeReport;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * The recorded facts a narrative is written from, and the checks run on the result.
 *
 * Everything here comes straight off the activity records — details as the officer
 * entered them, attendance as it was checked in — so the AI is only ever asked to
 * put recorded facts into prose, and the finished document can print the same
 * facts beside that prose. The review checks then compare what was written against
 * these facts, so an adviser can see before submitting where a draft leaves an
 * activity out, omits its attendance, or quotes a figure the records do not contain.
 */
class NarrativeReportData
{
    /** Facts for a single activity. */
    public static function activity(ClubActivity $activity): array
    {
        $sheet   = $activity->attendance()->with('user:id,first_name,last_name')->get();
        $present = $sheet->whereNotNull('time_in');

        $expected = (int) $activity->expected_participants;
        $attended = $present->count();

        return [
            'id'                  => $activity->id,
            'title'               => $activity->title,
            'type'                => $activity->activity_type_label,
            'is_meeting'          => $activity->isMeeting(),
            'date'                => $activity->date->format('F j, Y'),
            'time'                => self::clock($activity->time_start) . ' to ' . self::clock($activity->time_end),
            'venue'               => $activity->venue,
            'description'         => $activity->description,
            'purpose'             => $activity->purpose,
            'expected'            => $expected,
            'attendance_recorded' => $sheet->isNotEmpty(),
            'attended'            => $attended,
            'turnout'             => $expected > 0 ? round($attended / $expected * 100, 1) : null,
            'post_report'         => $activity->post_report_content ?: null,
            'approved_by'         => $activity->approval_status === 'approved' ? $activity->approvedBy?->name : null,
            'approved_on'         => $activity->approval_status === 'approved' ? $activity->approved_at?->format('F j, Y') : null,
            'attendee_ids'        => $present->pluck('user_id')->unique()->values()->all(),
            // Annex B of the accomplishment report is the attendance list itself, so the
            // names are kept with the report (names only — never e-mail or EDP numbers).
            'attendees'           => $present
                ->sortBy(fn ($row) => mb_strtolower((string) $row->user?->last_name . ' ' . $row->user?->first_name))
                ->map(fn ($row) => [
                    'name'    => $row->user ? trim("{$row->user->last_name}, {$row->user->first_name}") : 'Former member',
                    'time_in' => $row->time_in->format('g:i A'),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Facts for every activity a club completed within a semester, with totals.
     * Only completed activities dated inside the semester are included.
     */
    public static function semester(Club $club, Semester $semester): array
    {
        $activities = ClubActivity::where('club_id', $club->id)
            ->completed()
            ->whereDate('date', '>=', $semester->start_date->toDateString())
            ->whereDate('date', '<=', $semester->end_date->toDateString())
            ->orderBy('date')
            ->orderBy('time_start')
            ->get()
            ->map(fn (ClubActivity $activity) => self::activity($activity));

        $expected = $activities->sum('expected');
        $attended = $activities->sum('attended');

        $totals = [
            'activities'       => $activities->count(),
            'by_type'          => $activities->countBy('type')->all(),
            'attended'         => $attended,
            'unique_attendees' => $activities->pluck('attendee_ids')->flatten()->unique()->count(),
            'expected'         => $expected,
            'turnout'          => $expected > 0 ? round($attended / $expected * 100, 1) : null,
        ];

        $officers = $club->officers()->with('user:id,first_name,last_name')->get()
            ->mapWithKeys(fn ($officer) => [$officer->position => $officer->user?->name])
            ->filter()
            ->all();

        return [
            'club'          => $club->name,
            'club_acronym'  => $club->acronym,
            'adviser'       => $club->adviserUser?->name,
            'officers'      => $officers,
            'semester'      => $semester->label,
            'term'          => $semester->term,
            'academic_year' => $semester->academic_year,
            'start'         => $semester->start_date->format('F j, Y'),
            'end'           => $semester->end_date->format('F j, Y'),
            'totals'        => $totals,
            'fees'          => self::fees($club, $semester),
            // Who attended is only needed for the unique count; the names stay for Annex B.
            'activities'    => $activities->map(fn (array $a) => Arr::except($a, ['attendee_ids']))->values()->all(),
        ];
    }

    /**
     * The club's fee collections falling in the semester, for the financial report:
     * what each fee is, how many members it was charged to, how many paid, and the total
     * collected. A fee belongs to the semester its due date falls in (its creation date
     * when it has none).
     *
     * @return list<array{title: string, amount: float, billed: int, paid: int, collected: float}>
     */
    public static function fees(Club $club, Semester $semester): array
    {
        $start = $semester->start_date->copy()->startOfDay();
        $end   = $semester->end_date->copy()->endOfDay();

        // A cancelled fee was not collected, so it is not part of the club's account.
        return Fee::where('club_id', $club->id)
            ->notCancelled()
            ->withCount(['payments as billed', 'payments as paid' => fn ($q) => $q->where('status', 'paid')])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (Fee $fee) => ($fee->due_date ?? $fee->created_at)?->between($start, $end))
            ->map(fn (Fee $fee) => [
                'title'     => $fee->title,
                'amount'    => (float) $fee->amount,
                'billed'    => (int) $fee->billed,
                'paid'      => (int) $fee->paid,
                'collected' => round((float) $fee->amount * (int) $fee->paid, 2),
            ])
            ->values()
            ->all();
    }

    /** One activity's facts as the labelled text block a prompt carries. */
    public static function activityText(array $activity): string
    {
        $attendance = $activity['attendance_recorded']
            ? "{$activity['attended']}" . ($activity['turnout'] !== null ? " ({$activity['turnout']}% of expected)" : '')
            : 'not recorded (no attendance was taken)';

        return implode("\n", [
            "Title: \"{$activity['title']}\"",
            "Type: {$activity['type']}",
            "Date: {$activity['date']}",
            "Time: {$activity['time']}",
            "Venue: {$activity['venue']}",
            "Description (written by the officer before the activity): {$activity['description']}",
            "Purpose / objectives (written by the officer before the activity): {$activity['purpose']}",
            "Expected participants: {$activity['expected']}",
            "Actual attendees (from the attendance record): {$attendance}",
            "Officer's post-activity report: " . ($activity['post_report'] ?? 'none submitted'),
        ]);
    }

    /** The whole semester's facts as the text block a prompt carries. */
    public static function semesterText(array $data): string
    {
        $totals = $data['totals'];

        $types = collect($totals['by_type'])
            ->map(fn (int $count, string $type) => "{$type}: {$count}")
            ->implode('; ');

        $lines = [
            "Club: {$data['club']}",
            "Semester: {$data['semester']} ({$data['start']} to {$data['end']})",
            "Activities completed this semester: {$totals['activities']} ({$types})",
            "Total attendance across all activities: {$totals['attended']} check-ins by {$totals['unique_attendees']} different members",
            "Total expected participants across all activities: {$totals['expected']}"
                . ($totals['turnout'] !== null ? " (overall turnout {$totals['turnout']}% of expected)" : ''),
        ];

        foreach ($data['activities'] as $i => $activity) {
            $lines[] = '';
            $lines[] = 'Activity ' . ($i + 1);
            $lines[] = self::activityText($activity);
        }

        return implode("\n", $lines);
    }

    /**
     * Where a narrative falls short of the records it was written from.
     *
     * These are warnings for the adviser, not a gate: the adviser decides. Each one
     * names something checkable — an activity the text never mentions, an activity
     * whose attendance it does not state, or a figure that appears nowhere in the
     * records the draft was generated from.
     *
     * @return list<string>
     */
    public static function reviewWarnings(array $data, ?string $content): array
    {
        $content = trim((string) $content);

        if ($content === '') {
            return ['The narrative is empty.'];
        }

        $warnings   = [];
        $paragraphs = preg_split('/\n\s*\n/', $content) ?: [$content];

        foreach ($data['activities'] as $activity) {
            $mentions = array_values(array_filter(
                $paragraphs,
                fn (string $paragraph) => self::mentions($paragraph, $activity['title']),
            ));

            if ($mentions === []) {
                $warnings[] = "The narrative does not mention \"{$activity['title']}\".";
                continue;
            }

            if (! $activity['attendance_recorded']) {
                continue;
            }

            $figures = self::figures(implode("\n", $mentions));
            $missing = array_diff(
                [self::number((string) $activity['attended']), self::number((string) $activity['expected'])],
                $figures,
            );

            if ($missing !== []) {
                $warnings[] = "The narrative does not state the attendance for \"{$activity['title']}\" "
                    . "({$activity['attended']} attended of {$activity['expected']} expected).";
            }
        }

        $unsupported = array_values(array_diff(self::figures($content), self::figures(self::semesterText($data))));

        if ($unsupported !== []) {
            $warnings[] = 'These figures do not appear in the activity records: ' . implode(', ', $unsupported) . '.';
        }

        return $warnings;
    }

    /**
     * What the officers attached to an activity for the report — photo counts, the
     * budget, receipts and properties — as the labelled text a prompt carries. Built
     * from the same rows the documents print, so a figure the AI quotes from here is
     * one the document shows.
     */
    public static function recordsText(int $activityId, ?SemesterNarrativeReport $report = null): string
    {
        $activity = ClubActivity::find($activityId);
        $photos   = ActivityPhoto::where('club_activity_id', $activityId)
            ->when($report?->submitted_at, fn ($q) => $q->where('created_at', '<=', $report->submitted_at))
            ->orderBy('created_at')
            ->get();

        $counts = $photos->countBy('category');
        $lines  = [];

        $attached = collect(ActivityPhoto::CATEGORIES)
            ->filter(fn ($category, $key) => $counts->has($key) && ! in_array($key, ['receipt', 'property'], true))
            ->map(fn ($category, $key) => $counts[$key] . ' ' . Str::lower($category['label']))
            ->implode('; ');

        $lines[] = 'Photos attached by the officers: ' . ($attached !== '' ? $attached : 'none');

        if ($activity?->budget_amount !== null) {
            $lines[] = 'Budget released: PHP ' . number_format((float) $activity->budget_amount, 2);
        }

        $receipts = $photos->where('category', 'receipt');
        if ($receipts->isNotEmpty()) {
            $lines[] = 'Receipts (' . $receipts->count() . '), total PHP ' . number_format((float) $receipts->sum('amount'), 2) . ': '
                . $receipts->map(fn ($r) => "{$r->caption} PHP " . number_format((float) $r->amount, 2))->implode('; ');
        }

        $properties = $photos->where('category', 'property');
        if ($properties->isNotEmpty()) {
            $lines[] = 'Properties used: ' . $properties->map(fn ($p) => "{$p->caption} ({$p->quantity}" . ($p->remarks ? ", {$p->remarks}" : '') . ')')->implode('; ');
        }

        return implode("\n", $lines);
    }

    /**
     * Where the adviser's working copy of the per-activity sections falls short of the
     * records — the same kind of warnings as reviewWarnings(), one activity at a time.
     *
     * @param  array<int, array<string, string>>  $sections  Keyed by activity id.
     * @param  array<int, string>  $records  recordsText() for each activity, keyed by id.
     * @return list<string>
     */
    public static function sectionWarnings(array $data, array $sections, array $records = []): array
    {
        $warnings = [];
        $header   = implode("\n", [
            "Club: {$data['club']}",
            "Semester: {$data['semester']} ({$data['start']} to {$data['end']})",
        ]);

        foreach ($data['activities'] as $i => $activity) {
            $own   = $sections[$activity['id']] ?? [];
            $title = $activity['title'];

            foreach (SemesterNarrativeReport::SECTIONS as $key => $label) {
                if ($key === 'minutes' && empty($activity['is_meeting'])) {
                    continue;
                }

                if (trim((string) ($own[$key] ?? '')) === '') {
                    $warnings[] = "{$label} for \"{$title}\" is empty.";
                }
            }

            $introduction = (string) ($own['introduction'] ?? '');

            if ($activity['attendance_recorded'] && trim($introduction) !== '') {
                $missing = array_diff(
                    [self::number((string) $activity['attended']), self::number((string) $activity['expected'])],
                    self::figures($introduction),
                );

                if ($missing !== []) {
                    $warnings[] = "The introduction for \"{$title}\" does not state its attendance "
                        . "({$activity['attended']} attended of {$activity['expected']} expected).";
                }
            }

            $facts = implode("\n", [$header, 'Activity ' . ($i + 1) . ' of ' . count($data['activities']), self::activityText($activity), $records[$activity['id']] ?? '']);

            $unsupported = array_values(array_diff(
                self::figures(implode("\n", array_map('strval', $own))),
                self::figures($facts),
            ));

            if ($unsupported !== []) {
                $warnings[] = "These figures for \"{$title}\" do not appear in its records: " . implode(', ', $unsupported) . '.';
            }
        }

        return $warnings;
    }

    /** Whether a paragraph names the activity — by its full title, or one clear part of a "Part: Part" title. */
    private static function mentions(string $paragraph, string $title): bool
    {
        $haystack = self::normalise($paragraph);

        $candidates = array_filter(
            array_merge([$title], explode(':', $title)),
            fn (string $part) => str_word_count(trim($part)) >= 3 || $part === $title,
        );

        foreach ($candidates as $candidate) {
            if (str_contains($haystack, self::normalise($candidate))) {
                return true;
            }
        }

        return false;
    }

    private static function normalise(string $text): string
    {
        $text = str_replace(['“', '”', '‘', '’', '–', '—'], ['"', '"', "'", "'", '-', '-'], $text);

        return trim(preg_replace('/\s+/', ' ', Str::lower($text)));
    }

    /** @return list<string> Every distinct number in the text, normalised (08 → 8, 91.70 → 91.7). */
    private static function figures(string $text): array
    {
        preg_match_all('/\d+(?:\.\d+)?/', str_replace(',', '', $text), $matches);

        return array_values(array_unique(array_map(fn (string $n) => self::number($n), $matches[0])));
    }

    private static function number(string $n): string
    {
        return (string) (0 + $n);
    }

    private static function clock(?string $time): string
    {
        return $time ? Carbon::parse($time)->format('g:i A') : '—';
    }
}
