<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A CHED issuance kept in the administrator's library, with its signed PDF.
 */
class ChedMemorandum extends Model
{
    protected $table = 'ched_memorandums';

    public const TYPES = [
        'order'      => 'CHED Memorandum Order',
        'memorandum' => 'CHED Memorandum',
        'advisory'   => 'CHED Advisory',
    ];

    /** How each type is cited: "CMO No. 09, s. 2013". */
    private const CITATION_PREFIX = [
        'order'      => 'CMO',
        'memorandum' => 'CM',
        'advisory'   => 'Advisory',
    ];

    public const STATUSES = [
        'in_effect'  => 'In effect',
        'superseded' => 'Superseded',
        'revoked'    => 'Revoked',
    ];

    protected $fillable = [
        'type', 'memo_number', 'series_year', 'title', 'summary',
        'date_issued', 'effective_date', 'status', 'source_url',
        'file_path', 'file_name', 'file_size', 'uploaded_by',
    ];

    protected $casts = [
        'series_year'    => 'integer',
        'file_size'      => 'integer',
        'date_issued'    => 'date',
        'effective_date' => 'date',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getCitationAttribute(): string
    {
        $prefix = self::CITATION_PREFIX[$this->type] ?? 'CHED';

        return "{$prefix} No. {$this->memo_number}, s. {$this->series_year}";
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /** The name the file downloads under — the citation, not the uploader's file name. */
    public function downloadName(): string
    {
        $extension = pathinfo($this->file_name, PATHINFO_EXTENSION) ?: 'pdf';

        return str_replace([' ', ',', '.'], ['-', '', ''], $this->citation) . '.' . strtolower($extension);
    }

    /** Newest issuances first: by series, then by issue date. */
    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('series_year')->orderByDesc('date_issued')->orderByDesc('id');
    }
}
