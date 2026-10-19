<?php

namespace App\Services\NarrativeDocuments;

use App\Models\ActivityPhoto;
use App\Models\ClubActivity;
use App\Models\SemesterNarrativeReport;
use App\Services\ActivityPhotoStorage;
use App\Support\PdfImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Lays out the five semester-end documents the DSA collects, following the templates
 * in docs/resources-dsa: the SCC banner on every page, a cover with the Grand Student
 * Council and SCC logos and the club's own logo, a table of contents, then one part
 * per activity, meeting, transaction, property, letter or publication.
 *
 * Everything printed comes from the report: the activity facts frozen when it was
 * drafted, the sections the adviser reviewed, the fee collections, and the photos,
 * receipts and properties the officers attached. Where the records hold nothing for a
 * part of a template, the part is still there with a plain note, so the officers can
 * fill it in the Word file rather than find it missing.
 */
class DocumentBuilder
{
    public const DOCUMENTS = [
        'accomplishment' => [
            'label'       => 'Accomplishment & Documentation Report',
            'cover'       => 'ACCOMPLISHMENT & DOCUMENTATION REPORT',
            'prepared_by' => 'Club President',
            'contents'    => 'Every completed activity with its introduction, rationale, photos, attendance, program, minutes, letters and evaluation (Annexes A–F).',
        ],
        'minutes' => [
            'label'       => 'Minutes of Meetings',
            'cover'       => 'MINUTES OF MEETINGS',
            'prepared_by' => 'Club Secretary',
            'contents'    => 'The semester\'s meetings and the minutes of each, with the attached minutes.',
        ],
        'financial' => [
            'label'       => 'Financial Transactions & Liquidation Report',
            'cover'       => 'FINANCIAL TRANSACTIONS & LIQUIDATION REPORT',
            'prepared_by' => 'Club Treasurer & Auditor',
            'contents'    => 'Fee collections and each activity\'s budget, expenses and liquidation, with the receipts.',
        ],
        'inventory' => [
            'label'       => 'Property Inventory Report',
            'cover'       => 'PROPERTY INVENTORY REPORT',
            'prepared_by' => 'Club Officers',
            'landscape'   => true,
            'contents'    => 'The properties used for the semester\'s activities, with quantity and condition.',
        ],
        'communications' => [
            'label'       => 'Communication Letters & Publication Materials',
            'cover'       => 'COMMUNICATION LETTERS & PUBLICATION MATERIALS',
            'prepared_by' => 'Club PRO / Mass Media Officer',
            'contents'    => 'The communication letters and the information posted on social media.',
        ],
    ];

    private const BODY = 12;
    private const LABEL = 13;

    private array $data;

    /** @var array<int, array> Activity facts keyed by id. */
    private array $activities;

    /** @var Collection<int, Collection<string, Collection<int, ActivityPhoto>>> */
    private Collection $photos;

    /** @var array<int, ReportImage|string> Approval letters: an image, or 'pdf' when it cannot be printed. */
    private array $approvalLetters = [];

    private array $budgets;

    private ?ReportImage $clubLogo = null;

    public function __construct(private SemesterNarrativeReport $report)
    {
        $report->loadMissing('club', 'semester');

        $this->data       = $report->activity_data;
        $this->activities = collect($this->data['activities'])->keyBy('id')->all();

        $this->photos = ActivityPhoto::forReport($report)->get()
            ->filter(fn (ActivityPhoto $photo) => Storage::disk(ActivityPhotoStorage::DISK)->exists($photo->path))
            ->groupBy(['club_activity_id', 'category']);

        $rows = ClubActivity::whereIn('id', $report->activityIds())->get(['id', 'budget_amount', 'approval_letter_path']);

        $this->budgets = $rows->mapWithKeys(fn ($row) => [$row->id => $row->budget_amount !== null ? (float) $row->budget_amount : null])->all();

        foreach ($rows->whereNotNull('approval_letter_path') as $row) {
            $this->approvalLetters[$row->id] = $this->approvalLetter($row->approval_letter_path);
        }

        $this->clubLogo = ReportImage::fromDataUri(PdfImage::dataUri($report->club?->profile_photo_url, 480));
    }

    public static function label(string $key): string
    {
        return self::DOCUMENTS[$key]['label'] ?? Str::headline($key);
    }

    public function build(string $key): Document
    {
        $blocks = match ($key) {
            'accomplishment' => $this->accomplishment(),
            'minutes'        => $this->minutes(),
            'financial'      => $this->financial(),
            'inventory'      => $this->inventory(),
            'communications' => $this->communications(),
        };

        return new Document($key, self::label($key), (bool) (self::DOCUMENTS[$key]['landscape'] ?? false), $blocks);
    }

    /** What each document holds for this report, for the review and download pages. */
    public function summary(): array
    {
        $count = fn (string $category) => $this->photos->flatten()->where('category', $category)->count();

        return [
            'accomplishment' => count($this->activities) . ' ' . Str::plural('activity', count($this->activities)) . ', ' . $count('documentation') . ' ' . Str::plural('photo', $count('documentation')),
            'minutes'        => count($this->meetings()) . ' ' . Str::plural('meeting', count($this->meetings())) . ', ' . $count('minutes') . ' attached ' . Str::plural('page', $count('minutes')),
            'financial'      => count($this->financialRows()) . ' ' . Str::plural('transaction', count($this->financialRows())) . ', ' . $count('receipt') . ' ' . Str::plural('receipt', $count('receipt')),
            'inventory'      => $count('property') . ' ' . Str::plural('property', $count('property')),
            'communications' => count($this->letters()) . ' ' . Str::plural('letter', count($this->letters())) . ', ' . $count('publication') . ' publication ' . Str::plural('material', $count('publication')),
        ];
    }

    // ── Accomplishment & Documentation Report ────────────────────────────────

    private function accomplishment(): array
    {
        $blocks = [$this->cover('accomplishment'), ['type' => 'pagebreak']];

        $blocks[] = $this->heading('Table of Contents', 20);
        $blocks[] = $this->p('(List of Accomplished Activities/Event/Program)', ['italic' => true, 'size' => 13, 'align' => 'left']);
        $blocks[] = $this->table(
            [['label' => 'Name of the Activity/Event/Program', 'width' => 0.68], ['label' => 'Date Conducted', 'width' => 0.32]],
            array_map(fn ($a, $i) => [($i + 1) . '. ' . $a['title'], $a['date']], $this->data['activities'], array_keys($this->data['activities'])),
            'No activities were completed this semester.',
        );
        $blocks = array_merge($blocks, $this->signature('Submitted by:', $this->officer('president'), '(Club President)'));

        foreach ($this->data['activities'] as $i => $activity) {
            $n  = $i + 1;
            $id = $activity['id'];

            $blocks[] = ['type' => 'pagebreak'];
            $blocks[] = $this->heading($activity['title'], 16);
            $blocks[] = $this->p("Activity/Event No. {$n}", ['bold' => true, 'size' => self::LABEL, 'space_before' => 6]);
            $blocks[] = $this->field('Date:', $activity['date']);
            $blocks[] = $this->field('Time:', $activity['time']);
            $blocks[] = $this->field('Venue:', $activity['venue']);
            $blocks[] = $this->field('Type:', $activity['type']);
            $blocks[] = $this->p('Introduction', ['bold' => true, 'size' => self::LABEL, 'space_before' => 14]);
            array_push($blocks, ...$this->prose($this->section($id, 'introduction')));
            $blocks[] = $this->p('Rationale', ['bold' => true, 'size' => self::LABEL, 'space_before' => 14]);
            array_push($blocks, ...$this->prose($this->section($id, 'rationale')));

            array_push($blocks, ...$this->annex('A', ['PHOTOS', '(Series of Photos with caption and explanation)'], $n));
            $blocks[] = $this->photoBlock($this->photosOf($id, 'documentation'), 2, 'No photos were attached for this activity.');

            array_push($blocks, ...$this->annex('B', ['Attached List of Attendance'], $n));
            array_push($blocks, ...$this->attendance($activity));

            array_push($blocks, ...$this->annex('C', ['PROGRAM INVITATION', '(Attached Program Cover and Program Flow)'], $n));
            $blocks[] = $this->photoBlock($this->photosOf($id, 'program'), 1, 'No program or invitation was attached for this activity.');

            array_push($blocks, ...$this->annex('D', ['Minutes of Meetings in preparation of the Event'], $n));
            $blocks[] = $this->photoBlock($this->photosOf($id, 'minutes'), 1, 'No minutes were attached for this activity.');

            array_push($blocks, ...$this->annex('E', ['Approved/Communication Letters'], $n));
            if ($activity['approved_by'] ?? null) {
                $blocks[] = $this->p("Approved by {$activity['approved_by']}, Club Adviser, on {$activity['approved_on']}.", ['size' => self::BODY, 'align' => 'center', 'italic' => true]);
            }
            $blocks[] = $this->photoBlock($this->letterImages($id), 1, 'No communication letters were attached for this activity.');

            array_push($blocks, ...$this->annex('F', ['EVALUATION MEETING'], $n));
            $blocks[] = $this->field('Date:', '');
            $blocks[] = $this->field('Venue:', '');
            $blocks[] = $this->p('Attached copy of the Minutes of the Evaluation', ['italic' => true, 'size' => self::BODY, 'space_before' => 6]);
            $blocks[] = $this->p('SUMMARY RECOMMENDATIONS:', ['bold' => true, 'size' => self::LABEL, 'space_before' => 18]);
            array_push($blocks, ...$this->prose($this->section($id, 'recommendations')));
        }

        return $blocks;
    }

    private function annex(string $letter, array $titles, int $n): array
    {
        $blocks = [['type' => 'pagebreak'], $this->heading("ANNEX {$letter}", 18)];

        foreach ($titles as $title) {
            $blocks[] = str_starts_with($title, '(')
                ? $this->p($title, ['italic' => true, 'size' => self::BODY, 'align' => 'center'])
                : $this->heading($title, 15);
        }

        $blocks[] = $this->p("Activity/Event No. {$n}", ['bold' => true, 'size' => self::LABEL, 'align' => 'center', 'space_after' => 14]);

        return $blocks;
    }

    private function attendance(array $activity): array
    {
        if (! $activity['attendance_recorded']) {
            return [$this->p('No attendance was recorded for this activity.', ['italic' => true, 'size' => self::BODY, 'align' => 'center'])];
        }

        $rows = array_map(
            fn ($attendee, $i) => [(string) ($i + 1), $attendee['name'], $attendee['time_in']],
            $activity['attendees'] ?? [],
            array_keys($activity['attendees'] ?? []),
        );

        return [
            $this->p("{$activity['attended']} of {$activity['expected']} expected participants attended, as checked in on the attendance sheet.", ['size' => self::BODY, 'align' => 'center', 'space_after' => 8]),
            $this->table(
                [['label' => 'No.', 'width' => 0.1, 'align' => 'center'], ['label' => 'Name', 'width' => 0.62], ['label' => 'Time In', 'width' => 0.28, 'align' => 'center']],
                $rows,
                'No attendee names are on record.',
            ),
        ];
    }

    // ── Minutes of Meetings ──────────────────────────────────────────────────

    /** Meetings, and any other activity the officers attached minutes to. */
    private function meetings(): array
    {
        return array_values(array_filter(
            $this->data['activities'],
            fn ($a) => ! empty($a['is_meeting']) || $this->photosOf($a['id'], 'minutes') !== [],
        ));
    }

    private function minutes(): array
    {
        $meetings = $this->meetings();
        $blocks   = [$this->cover('minutes'), ['type' => 'pagebreak']];

        $blocks[] = $this->heading('Table of Contents', 20);
        $blocks[] = $this->p('(List of Meetings)', ['italic' => true, 'size' => 13, 'align' => 'left']);
        $blocks[] = $this->table(
            [['label' => 'Name of Meeting', 'width' => 0.68], ['label' => 'Date', 'width' => 0.32]],
            array_map(fn ($m, $i) => [($i + 1) . '. ' . $m['title'], $m['date']], $meetings, array_keys($meetings)),
            'No meetings were held this semester.',
        );
        $blocks = array_merge($blocks, $this->signature('Prepared by:', null, 'Club Secretary'));

        foreach ($meetings as $meeting) {
            $blocks[] = ['type' => 'pagebreak'];
            $blocks[] = $this->heading('Attached Minutes of Meeting', 18);
            $blocks[] = $this->heading($meeting['title'], 14);
            $blocks[] = $this->field('Date:', $meeting['date']);
            $blocks[] = $this->field('Time:', $meeting['time']);
            $blocks[] = $this->field('Venue:', $meeting['venue']);
            $blocks[] = $this->p('', ['size' => 6]);
            array_push($blocks, ...$this->prose($this->section($meeting['id'], 'minutes')));

            $pages = $this->photosOf($meeting['id'], 'minutes');
            if ($pages !== []) {
                // Short enough for the first page to fit under the meeting's details, so
                // the heading never ends a page on its own.
                $blocks[] = $this->p('Attached minutes', ['bold' => true, 'size' => self::LABEL, 'space_before' => 14]);
                $blocks[] = $this->photoBlock($pages, 1, '', 470);
            }
        }

        return $blocks;
    }

    // ── Financial Transactions & Liquidation Report ──────────────────────────

    /** Activities with a budget or receipts, in the order they were held. */
    private function financialActivities(): array
    {
        return array_values(array_filter(
            $this->data['activities'],
            fn ($a) => ($this->budgets[$a['id']] ?? null) !== null || $this->photosOf($a['id'], 'receipt') !== [],
        ));
    }

    private function financialRows(): array
    {
        $rows = [];

        foreach ($this->financialActivities() as $i => $activity) {
            $budget   = $this->budgets[$activity['id']] ?? null;
            $receipts = $this->photosOf($activity['id'], 'receipt');
            $expenses = $receipts === [] ? null : array_sum(array_map(fn ($r) => (float) $r['photo']->amount, $receipts));

            $remarks = match (true) {
                $budget === null              => 'No budget recorded',
                $expenses === null            => 'No receipts attached',
                round($budget - $expenses, 2) < 0  => 'Deficit ' . $this->money($expenses - $budget),
                round($budget - $expenses, 2) > 0  => 'Remaining balance ' . $this->money($budget - $expenses),
                default                       => 'Fully liquidated',
            };

            $rows[] = ['Activity ' . ($i + 1) . ': ' . $activity['title'], $this->money($budget), $this->money($expenses), $remarks];
        }

        foreach ($this->data['fees'] ?? [] as $fee) {
            $rows[] = [
                'Collection of ' . $fee['title'],
                $this->money($fee['collected']),
                '—',
                "Collected from {$fee['paid']} of {$fee['billed']} members at " . $this->money($fee['amount']) . ' each',
            ];
        }

        return $rows;
    }

    private function financial(): array
    {
        $blocks = [$this->cover('financial'), ['type' => 'pagebreak']];

        $blocks[] = $this->heading('Table of Contents', 20);
        $blocks[] = $this->p('(List of Financial Transactions)', ['italic' => true, 'size' => 13, 'align' => 'left']);
        $blocks[] = $this->table(
            [
                ['label' => 'Name of Activity/Event', 'width' => 0.36],
                ['label' => 'Amount', 'width' => 0.19, 'align' => 'right'],
                ['label' => 'Total Expenses', 'width' => 0.19, 'align' => 'right'],
                ['label' => 'Remarks', 'width' => 0.26],
            ],
            $this->financialRows(),
            'No fee collections, budgets or receipts were recorded this semester.',
        );
        $blocks = array_merge(
            $blocks,
            $this->signature('Prepared by:', $this->officer('treasurer'), '(Club Treasurer)'),
            $this->signature('Audited by:', null, '(Club Auditor)'),
        );

        foreach ($this->financialActivities() as $i => $activity) {
            $n        = $i + 1;
            $id       = $activity['id'];
            $budget   = $this->budgets[$id] ?? null;
            $receipts = $this->photosOf($id, 'receipt');

            $blocks[] = ['type' => 'pagebreak'];
            $blocks[] = $this->heading($activity['title'], 16);
            $blocks[] = $this->p("Activity/Event No. {$n}", ['bold' => true, 'size' => self::LABEL, 'space_before' => 6]);
            $blocks[] = $this->field('Date:', $activity['date']);
            $blocks[] = $this->field('Venue:', $activity['venue']);
            $blocks[] = $this->p('Introduction', ['bold' => true, 'size' => self::LABEL, 'space_before' => 14]);
            array_push($blocks, ...$this->prose($this->section($id, 'introduction')));

            $blocks[] = ['type' => 'pagebreak'];
            $blocks[] = $this->heading('Copy of the Approved Communication Letter and Budget Breakdown', 15);
            $blocks[] = $this->p("Activity/Event No. {$n}", ['bold' => true, 'size' => self::LABEL, 'align' => 'center', 'space_after' => 14]);
            $blocks[] = $this->photoBlock($this->letterImages($id), 1, 'No approved letter or budget breakdown was attached for this activity.');

            $blocks[] = ['type' => 'pagebreak'];
            $blocks[] = $this->heading('LIQUIDATION', 18);
            $blocks[] = $this->p("Activity/Event No. {$n}", ['bold' => true, 'size' => self::LABEL, 'align' => 'center', 'space_after' => 14]);
            $blocks[] = $this->field('Budget Amount Released:', $budget === null ? '' : $this->money($budget));
            $blocks[] = $this->field('Date Audited:', '');
            $blocks[] = $this->p('Summary', ['bold' => true, 'size' => self::LABEL, 'align' => 'center', 'space_before' => 10]);
            $blocks[] = $this->table(
                [['label' => 'Receipt No.', 'width' => 0.24], ['label' => 'Item/Description', 'width' => 0.52], ['label' => 'Amount', 'width' => 0.24, 'align' => 'right']],
                array_map(fn ($r) => [(string) $r['photo']->reference_number, (string) $r['photo']->caption, $this->money((float) $r['photo']->amount)], $receipts),
                'No receipts were attached for this activity.',
                $receipts === [] ? null : ['', 'TOTAL', $this->money(array_sum(array_map(fn ($r) => (float) $r['photo']->amount, $receipts)))],
            );

            $blocks[] = ['type' => 'pagebreak'];
            $blocks[] = $this->heading('Attached Copy of Liquidation Report or Receipts', 15);
            $blocks[] = $this->p("Activity/Event No. {$n}", ['bold' => true, 'size' => self::LABEL, 'align' => 'center', 'space_after' => 14]);
            $blocks[] = $this->photoBlock(
                array_map(fn ($r) => ['image' => $r['image'], 'caption' => trim($r['photo']->caption . ' — ' . $this->money((float) $r['photo']->amount)
                    . ($r['photo']->reference_number ? " (Receipt No. {$r['photo']->reference_number})" : ''))], $receipts),
                2,
                'No receipts were attached for this activity.',
            );
        }

        return $blocks;
    }

    // ── Property Inventory Report ────────────────────────────────────────────

    private function inventory(): array
    {
        $properties = [];
        foreach ($this->data['activities'] as $activity) {
            foreach ($this->photosOf($activity['id'], 'property') as $property) {
                $properties[] = $property + ['activity' => $activity['title']];
            }
        }

        $blocks = [$this->cover('inventory'), ['type' => 'pagebreak']];

        $blocks[] = $this->heading(Str::upper($this->data['club']) . ' INVENTORY', 18);
        $blocks[] = $this->p($this->termText() . ($this->data['academic_year'] ? ' S.Y ' . $this->data['academic_year'] : ''), ['bold' => true, 'size' => 14, 'align' => 'center', 'space_after' => 12]);
        $blocks[] = $this->table(
            [
                ['label' => 'List of Properties', 'width' => 0.34],
                ['label' => 'Quantity', 'width' => 0.12, 'align' => 'center'],
                ['label' => 'Remarks', 'width' => 0.24],
                ['label' => 'Used for (Activity/Event)', 'width' => 0.30],
            ],
            array_map(fn ($p) => [(string) $p['photo']->caption, (string) $p['photo']->quantity, (string) $p['photo']->remarks, $p['activity']], $properties),
            'No properties were recorded for this semester\'s activities.',
        );
        $blocks = array_merge($blocks, $this->signature('Prepared by:', null, '(Club Officer)'));

        if ($properties !== []) {
            $blocks[] = ['type' => 'pagebreak'];
            $blocks[] = $this->heading('Attached Photos of Properties', 16);
            $blocks[] = $this->photoBlock(
                array_map(fn ($p) => ['image' => $p['image'], 'caption' => "{$p['photo']->caption} ({$p['photo']->quantity})"], $properties),
                2,
                '',
            );
        }

        return $blocks;
    }

    // ── Communication Letters & Publication Materials ────────────────────────

    /** Letters the officers attached, then approval letters uploaded with an activity. */
    private function letters(): array
    {
        $letters = [];

        foreach ($this->data['activities'] as $activity) {
            foreach ($this->photosOf($activity['id'], 'letter') as $letter) {
                $letters[] = ['name' => $letter['photo']->caption ?: "Communication letter for {$activity['title']}", 'image' => $letter['image']];
            }

            if (isset($this->approvalLetters[$activity['id']])) {
                $image     = $this->approvalLetters[$activity['id']];
                $letters[] = $image instanceof ReportImage
                    ? ['name' => "Letter for approval of {$activity['title']}", 'image' => $image]
                    : ['name' => "Letter for approval of {$activity['title']} (PDF on file in ClubSync)", 'image' => null];
            }
        }

        return $letters;
    }

    private function communications(): array
    {
        $letters = $this->letters();
        $pubmats = [];
        foreach ($this->data['activities'] as $activity) {
            foreach ($this->photosOf($activity['id'], 'publication') as $pubmat) {
                $pubmats[] = ['name' => $pubmat['photo']->caption ?: "Publication material for {$activity['title']}", 'image' => $pubmat['image']];
            }
        }

        $blocks = [$this->cover('communications'), ['type' => 'pagebreak']];

        $blocks[] = $this->heading('Table of Contents', 20);
        $blocks[] = $this->p('(List of Communication Letters)', ['italic' => true, 'size' => 13, 'align' => 'left']);
        $blocks[] = $this->table(
            [['label' => 'Name of Communication', 'width' => 1.0]],
            array_map(fn ($l, $i) => [($i + 1) . '. ' . $l['name']], $letters, array_keys($letters)),
            'No communication letters were attached this semester.',
        );

        foreach (array_filter($letters, fn ($l) => $l['image'] !== null) as $letter) {
            $blocks[] = ['type' => 'pagebreak'];
            $blocks[] = $this->photoBlock([['image' => $letter['image'], 'caption' => '']], 1, '', 700);
            $blocks[] = $this->heading('Attached Communication Letter', 14);
            $blocks[] = $this->p($letter['name'], ['italic' => true, 'size' => self::BODY, 'align' => 'center']);
        }

        $blocks[] = ['type' => 'pagebreak'];
        $blocks[] = $this->heading('Table of Contents', 20);
        $blocks[] = $this->p('(List of Information posted in social media)', ['italic' => true, 'size' => 13, 'align' => 'left']);
        $blocks[] = $this->table(
            [['label' => 'Name of Information', 'width' => 1.0]],
            array_map(fn ($m, $i) => [($i + 1) . '. ' . $m['name']], $pubmats, array_keys($pubmats)),
            'No publication materials were attached this semester.',
        );

        foreach ($pubmats as $pubmat) {
            $blocks[] = ['type' => 'pagebreak'];
            $blocks[] = $this->photoBlock([['image' => $pubmat['image'], 'caption' => '']], 1, '', 700);
            $blocks[] = $this->heading('Attached Photo of Information posted in Social Media', 14);
            $blocks[] = $this->p($pubmat['name'], ['italic' => true, 'size' => self::BODY, 'align' => 'center']);
        }

        return $blocks;
    }

    // ── Shared parts ─────────────────────────────────────────────────────────

    private function cover(string $key): array
    {
        $lines = [];

        if ($key === 'communications') {
            // Since the post was split, the internal media mass officer signs; failing
            // that the external one, then anyone still holding the old single post.
            $mmo     = $this->officer('mmo_internal') ?? $this->officer('mmo_external') ?? $this->officer('mmo');
            $lines[] = [['text' => $mmo ?? '(Name)', 'bold' => true, 'underline' => $mmo !== null]];
            $lines[] = [['text' => $mmo ? 'Mass Media Officer' : '(Position)']];
        }

        return [
            'type'      => 'cover',
            'title'     => self::DOCUMENTS[$key]['cover'],
            'term'      => $this->termRuns(),
            'year'      => $this->data['academic_year'] ? 'S.Y ' . $this->data['academic_year'] : null,
            'logos'     => array_values(array_filter([self::brand('gsc-logo.png'), self::brand('scc-seal.png')])),
            'club'      => $this->data['club'],
            'club_logo' => $this->clubLogo,
            'lines'     => $lines,
            'date'      => ($this->report->submitted_at ?? now())->format('F j, Y'),
        ];
    }

    /** "1st SEMESTER" with the ordinal raised, as the templates print it. */
    private function termRuns(): array
    {
        return match ($this->data['term'] ?? null) {
            'first'  => [['text' => '1'], ['text' => 'st', 'sup' => true], ['text' => ' SEMESTER']],
            'second' => [['text' => '2'], ['text' => 'nd', 'sup' => true], ['text' => ' SEMESTER']],
            'summer' => [['text' => 'SUMMER TERM']],
            default  => [['text' => Str::upper($this->data['semester'])]],
        };
    }

    private function termText(): string
    {
        return implode('', array_column($this->termRuns(), 'text'));
    }

    private function signature(string $label, ?string $name, string $role): array
    {
        return [
            $this->p($label, ['size' => self::LABEL, 'space_before' => 36]),
            $this->p($name ?? '(Name and Signature)', ['size' => self::LABEL, 'bold' => $name !== null, 'underline' => true, 'italic' => $name === null, 'space_before' => 18]),
            $this->p($role, ['size' => self::LABEL]),
        ];
    }

    private function heading(string $text, float $size): array
    {
        return $this->p($text, ['bold' => true, 'size' => $size, 'align' => 'center', 'space_after' => 6]);
    }

    private function field(string $label, string $value): array
    {
        return [
            'type'        => 'paragraph',
            'runs'        => [['text' => $label, 'bold' => true], ['text' => $value === '' ? ' ____________________' : " {$value}"]],
            'size'        => self::LABEL,
            'align'       => 'left',
            'space_after' => 4,
        ];
    }

    private function p(string $text, array $options = []): array
    {
        return array_merge([
            'type'        => 'paragraph',
            'runs'        => [['text' => $text, 'bold' => $options['bold'] ?? false, 'italic' => $options['italic'] ?? false, 'underline' => $options['underline'] ?? false]],
            'size'        => self::BODY,
            'align'       => 'left',
            'space_after' => 6,
        ], array_diff_key($options, array_flip(['bold', 'italic', 'underline'])));
    }

    /**
     * A drafted section as paragraphs: prose justified with a first-line indent,
     * "- " lines as bullets, and "Label: value" lines (the minutes) with the label bold.
     */
    private function prose(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [$this->p('(To be written.)', ['italic' => true])];
        }

        $blocks = [];

        foreach (preg_split('/\n+/', $text) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^[-•*]\s+(.*)$/u', $line, $m)) {
                $blocks[] = ['type' => 'paragraph', 'runs' => [['text' => $m[1]]], 'size' => self::BODY, 'align' => 'left', 'bullet' => true, 'space_after' => 4];
            } elseif (preg_match('/^([A-Z][A-Za-z ]{1,24}):\s+(.+)$/', $line, $m)) {
                $blocks[] = ['type' => 'paragraph', 'runs' => [['text' => "{$m[1]}:", 'bold' => true], ['text' => " {$m[2]}"]], 'size' => self::BODY, 'align' => 'left', 'space_after' => 6];
            } else {
                $blocks[] = ['type' => 'paragraph', 'runs' => [['text' => $line]], 'size' => self::BODY, 'align' => 'justify', 'indent' => true, 'space_after' => 8, 'line' => 1.15];
            }
        }

        return $blocks;
    }

    private function table(array $columns, array $rows, string $empty, ?array $total = null): array
    {
        return ['type' => 'table', 'columns' => $columns, 'rows' => $rows, 'total' => $total, 'empty' => $empty, 'size' => self::BODY];
    }

    /** @param  list<array{image: ReportImage, caption: string}>  $photos */
    private function photoBlock(array $photos, int $columns, string $empty, ?float $maxHeight = null): array
    {
        return [
            'type'       => 'photos',
            'photos'     => array_map(fn ($p) => ['image' => $p['image'], 'caption' => $p['caption'] ?? ''], $photos),
            'columns'    => $columns,
            'max_height' => $maxHeight ?? ($columns === 1 ? 560.0 : 220.0),
            'empty'      => $empty,
        ];
    }

    /** @return list<array{image: ReportImage, caption: string, photo: ActivityPhoto}> */
    private function photosOf(int $activityId, string $category): array
    {
        return ($this->photos->get($activityId)?->get($category) ?? collect())
            ->map(fn (ActivityPhoto $photo) => [
                'image'   => new ReportImage('image/jpeg', $photo->width, $photo->height, fn () => (string) ActivityPhotoStorage::contents($photo)),
                'caption' => (string) $photo->caption,
                'photo'   => $photo,
            ])
            ->values()
            ->all();
    }

    /** The letters attached to an activity, the approval letter first when it is a picture. */
    private function letterImages(int $activityId): array
    {
        $images = $this->photosOf($activityId, 'letter');
        $letter = $this->approvalLetters[$activityId] ?? null;

        if ($letter instanceof ReportImage) {
            array_unshift($images, ['image' => $letter, 'caption' => 'Letter for approval uploaded with the activity']);
        }

        return $images;
    }

    private function approvalLetter(string $path): ReportImage|string|null
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            return null;
        }

        if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true)) {
            return 'pdf';
        }

        $jpeg = ActivityPhotoStorage::jpegFromBytes((string) $disk->get($path));

        return $jpeg ? new ReportImage('image/jpeg', $jpeg[1], $jpeg[2], fn () => $jpeg[0]) : null;
    }

    private function section(int $activityId, string $section): string
    {
        return $this->report->section($activityId, $section);
    }

    private function officer(string $position): ?string
    {
        return $this->data['officers'][$position] ?? null;
    }

    private function money(?float $amount): string
    {
        return $amount === null ? '—' : 'Php ' . number_format($amount, 2);
    }

    /** One of the SCC images in public/images/reports. */
    public static function brand(string $file): ?ReportImage
    {
        static $cache = [];

        return $cache[$file] ??= ReportImage::fromFile(public_path("images/reports/{$file}"));
    }
}
