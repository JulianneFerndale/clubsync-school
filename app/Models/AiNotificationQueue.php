<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiNotificationQueue extends Model
{
    protected $table = 'ai_notification_queue';

    protected $fillable = [
        'club_id', 'gap_type', 'description', 'draft_content', 'dsa_edited_content',
        'status', 'reviewed_by', 'reviewed_at', 'sent_at', 'ai_available',
        'is_resolved', 'resolution_note', 'resolved_at', 'resolved_by',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'sent_at'     => 'datetime',
        'resolved_at' => 'datetime',
        'ai_available' => 'boolean',
        'is_resolved'  => 'boolean',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function finalContent(): ?string
    {
        return $this->dsa_edited_content ?: $this->draft_content;
    }

    /**
     * A human-readable name for the compliance gap. `gap_type` is a database slug
     * and must never reach a notification, an email or the AI prompt as-is — the
     * reader has to be told which requirement was missed.
     */
    public function getGapLabelAttribute(): string
    {
        return match ($this->gap_type) {
            'overdue_presence_update'  => 'Overdue semestral presence update',
            'missing_ched_report'      => 'Missing CHED report',
            'overdue_activity_request' => 'Overdue activity approval request',
            'unupdated_officer_info'   => 'Officer records not updated',
            default                    => ucfirst(str_replace('_', ' ', (string) $this->gap_type)),
        };
    }
}
