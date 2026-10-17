<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One decision on a student's proof of enrollment (COE or school ID).
 *
 * The document itself is kept only while status is `pending` — see
 * App\Services\EnrollmentVerifier. `findings` is what the automatic check read off the
 * document (encrypted at rest, POLICY.md §Data Privacy) and is cleared with the file.
 */
class EnrollmentVerification extends Model
{
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_PENDING  = 'pending';
    public const STATUS_REJECTED = 'rejected';

    /** Why a person, not the automatic check, has to decide. */
    public const REASON_AI_UNAVAILABLE   = 'ai_unavailable';
    public const REASON_STUDENT_REQUEST  = 'student_request';
    public const REASON_FLAGGED          = 'flagged';

    protected $fillable = [
        'user_id', 'status', 'method', 'review_reason',
        'document_type', 'document_path', 'document_mime',
        'findings', 'remarks', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'findings'    => 'encrypted:array',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getDocumentLabelAttribute(): string
    {
        return self::documentLabel($this->document_type);
    }

    public static function documentLabel(?string $type): string
    {
        return match ($type) {
            'certificate_of_enrollment' => 'Certificate of Enrollment',
            'school_id'                 => 'SCC school ID (front)',
            'school_id_back'            => 'SCC school ID (back)',
            'other'                     => 'Not a COE or school ID',
            default                     => 'Not identified',
        };
    }

    public function getReviewReasonLabelAttribute(): string
    {
        return match ($this->review_reason) {
            self::REASON_AI_UNAVAILABLE  => 'The automatic check was unavailable — ClubSync re-checks it by itself once the AI is back',
            self::REASON_STUDENT_REQUEST => 'The student asked for a manual review after the automatic check failed',
            self::REASON_FLAGGED         => 'The automatic check flagged the document for a closer look',
            default                      => '—',
        };
    }
}
