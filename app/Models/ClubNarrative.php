<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubNarrative extends Model
{
    protected $fillable = [
        'club_id', 'club_activity_id', 'title', 'draft_content', 'adviser_edited_content',
        'status', 'ai_available', 'reviewed_by', 'reviewed_at', 'published_at',
    ];

    protected $casts = [
        'ai_available' => 'boolean',
        'reviewed_at'  => 'datetime',
        'published_at' => 'datetime',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ClubActivity::class, 'club_activity_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function finalContent(): ?string
    {
        return $this->adviser_edited_content ?: $this->draft_content;
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->orderByDesc('published_at');
    }

    public function scopePendingReview($query)
    {
        return $query->where('status', 'pending_review');
    }
}
