<?php

namespace App\Models;

use App\Services\NarrativeReportData;
use App\Services\NarrativeTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A club's end-of-semester narrative report for the DSA.
 *
 * The report is the five documents the DSA collects each semester (accomplishment,
 * minutes, financial, property inventory, communications). Gemini drafts the written
 * sections for each activity once the semester ends; the club adviser reviews and
 * edits them, then submits the documents to the DSA as Word files that can also be
 * downloaded as PDFs. Nothing reaches the DSA until the adviser submits it
 * (POLICY.md § AI & Automation).
 *
 * Reports drafted before the five documents existed are "legacy": one narrative in
 * draft_content, submitted as a single PDF. They keep working as they were.
 *
 * The DSA may return a submitted report for revision. It goes back to pending_review
 * with the DSA's remarks (returned_at, returned_by, return_remarks), the adviser
 * revises and submits it again, and the documents are frozen afresh at that point.
 */
class SemesterNarrativeReport extends Model
{
    /** The written sections Gemini drafts for every activity, in the order the review page shows them. */
    public const SECTIONS = [
        'introduction'    => 'Introduction',
        'rationale'       => 'Rationale',
        'minutes'         => 'Minutes of the meeting',
        'recommendations' => 'Summary recommendations',
    ];

    protected $fillable = [
        'club_id', 'semester_id', 'activity_data', 'draft_content', 'adviser_edited_content',
        'ai_sections', 'sections',
        'ai_available', 'status', 'pdf_path', 'reviewed_by', 'reviewed_at', 'submitted_at',
        'returned_at', 'returned_by', 'return_remarks',
    ];

    protected $casts = [
        'activity_data' => 'array',
        'ai_sections'   => 'array',
        'sections'      => 'array',
        'ai_available'  => 'boolean',
        'reviewed_at'   => 'datetime',
        'submitted_at'  => 'datetime',
        'returned_at'   => 'datetime',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** The DSA who last returned the report for revision. */
    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function finalContent(): ?string
    {
        return $this->adviser_edited_content ?: $this->draft_content;
    }

    /** @return list<string> Where the current narrative falls short of the activity records. */
    public function reviewWarnings(): array
    {
        if (! $this->isLegacy()) {
            $records = collect($this->activityIds())
                ->mapWithKeys(fn (int $id) => [$id => NarrativeReportData::recordsText($id, $this)])
                ->all();

            return NarrativeReportData::sectionWarnings($this->activity_data, $this->sections ?? [], $records);
        }

        return NarrativeReportData::reviewWarnings($this->activity_data, $this->finalContent());
    }

    /** Drafted before the five documents: a single narrative, submitted as one PDF. */
    public function isLegacy(): bool
    {
        return $this->sections === null;
    }

    /** @return list<int> The activities the report covers. */
    public function activityIds(): array
    {
        return array_map('intval', array_column($this->activity_data['activities'] ?? [], 'id'));
    }

    /** The adviser's working copy of one section of one activity. */
    public function section(int $activityId, string $section): string
    {
        return (string) ($this->sections[$activityId][$section] ?? '');
    }

    /**
     * Where an activity's first draft came from: 'ai', 'template' (written from its
     * records because the AI could not answer — NarrativeTemplate) or 'none' (blank).
     *
     * ai_sections keeps each activity's untouched first draft beside the adviser's copy
     * in `sections`; a template draft carries `source: template` there.
     */
    public function draftSource(int $activityId): string
    {
        $first = $this->ai_sections[$activityId] ?? [];

        if (($first['source'] ?? null) === NarrativeTemplate::SOURCE) {
            return NarrativeTemplate::SOURCE;
        }

        return trim((string) ($first['introduction'] ?? '')) !== '' ? 'ai' : 'none';
    }

    /** Whether the adviser has left an activity's sections exactly as first drafted. */
    public function sectionsUntouched(int $activityId): bool
    {
        $first = $this->ai_sections[$activityId] ?? [];
        $own   = $this->sections[$activityId] ?? [];

        foreach (array_keys(self::SECTIONS) as $key) {
            if ((string) ($own[$key] ?? '') !== (string) ($first[$key] ?? '')) {
                return false;
            }
        }

        return true;
    }

    /** Whether the adviser changed anything in the first drafts. */
    public function sectionsEdited(): bool
    {
        if ($this->isLegacy()) {
            return false;
        }

        foreach ($this->activityIds() as $id) {
            if (! $this->sectionsUntouched($id)) {
                return true;
            }
        }

        return false;
    }

    /** ai_available means the AI drafted every activity — a template draft is not one. */
    public function syncAiAvailable(): void
    {
        $this->ai_available = collect($this->activityIds())
            ->every(fn (int $id) => $this->draftSource($id) === 'ai');
    }

    /** Where the generated documents of a submitted report are kept. */
    public function documentDirectory(): string
    {
        return "reports/semester-narratives/{$this->id}";
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    /** Back with the adviser because the DSA asked for a revision, not yet resubmitted. */
    public function isReturned(): bool
    {
        return $this->status === 'pending_review' && $this->returned_at !== null;
    }

    /** What the status badge shows: a returned report reads "Revision Needed", not "Pending". */
    public function badgeStatus(): string
    {
        return $this->isReturned() ? 'revision_required' : $this->status;
    }

    public function scopePendingReview($query)
    {
        return $query->where('status', 'pending_review');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }
}
