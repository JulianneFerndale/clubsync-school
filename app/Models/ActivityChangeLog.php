<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityChangeLog extends Model
{
    protected $fillable = ['club_activity_id', 'changed_by', 'changes'];

    protected $casts = [
        'changes' => 'array',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ClubActivity::class, 'club_activity_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
