<?php

namespace App\Models;

use App\Services\NarrativeReportData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A club's end-of-semester narrative report for the DSA.
 *
 * Drafted by Gemini once the semester ends, reviewed and edited by the club adviser,
 * then submitted to the DSA as a PDF. Nothing reaches the DSA until the adviser
 * submits it (POLICY.md § AI & Automation).
 */
class SemesterNarrativeReport extends Model
{
    protected $fillable = [
        'club_id', 'semester_id', 'activity_data', 'draft_content', 'adviser_edited_content',
        'ai_available', 'status', 'pdf_path', 'reviewed_by', 'reviewed_at', 'submitted_at',
    ];

    protected $casts = [
        'activity_data' => 'array',
        'ai_available'  => 'boolean',
        'reviewed_at'   => 'datetime',
        'submitted_at'  => 'datetime',
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

    public function finalContent(): ?string
    {
        return $this->adviser_edited_content ?: $this->draft_content;
    }

    /** @return list<string> Where the current narrative falls short of the activity records. */
    public function reviewWarnings(): array
    {
        return NarrativeReportData::reviewWarnings($this->activity_data, $this->finalContent());
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
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
