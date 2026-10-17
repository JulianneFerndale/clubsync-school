<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A downloadable file the administrator publishes to every user: the SCC club
 * handbook, or a report template clubs fill in.
 */
class ResourceDocument extends Model
{
    public const CATEGORIES = [
        'handbook' => 'Club Handbook',
        'template' => 'Report Template',
    ];

    protected $fillable = [
        'category', 'report_type', 'title', 'description',
        'file_path', 'file_name', 'file_size', 'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }

    public function getExtensionAttribute(): string
    {
        return strtoupper(pathinfo($this->file_name, PATHINFO_EXTENSION));
    }

    public function scopeHandbooks($query)
    {
        return $query->where('category', 'handbook');
    }

    public function scopeTemplates($query)
    {
        return $query->where('category', 'template');
    }
}
