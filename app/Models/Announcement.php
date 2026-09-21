<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    protected $fillable = [
        'club_id', 'author_id', 'title', 'content', 'type', 'status',
        'ai_assisted', 'adviser_notes', 'reviewed_by', 'reviewed_at', 'published_at',
    ];

    protected $casts = [
        'ai_assisted'  => 'boolean',
        'reviewed_at'  => 'datetime',
        'published_at' => 'datetime',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(AnnouncementLike::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AnnouncementComment::class)->orderBy('created_at');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->orderByDesc('published_at');
    }
}
