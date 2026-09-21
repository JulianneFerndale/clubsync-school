<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChedReport extends Model
{
    protected $fillable = [
        'club_activity_id', 'club_id', 'report_type',
        'pdf_path', 'xlsx_path', 'narrative', 'is_finalized',
    ];

    protected $casts = [
        'is_finalized' => 'boolean',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ClubActivity::class, 'club_activity_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
