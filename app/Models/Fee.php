<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fee extends Model
{
    public const ACTIVE    = 'active';
    public const POSTPONED = 'postponed';
    public const CANCELLED = 'cancelled';

    protected $fillable = ['club_id', 'title', 'amount', 'due_date', 'academic_period'];

    protected $attributes = [
        'status' => self::ACTIVE,
    ];

    protected $casts = [
        'amount'            => 'decimal:2',
        'due_date'          => 'date',
        'previous_due_date' => 'date',
        'status_changed_at' => 'datetime',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FeePayment::class);
    }

    /**
     * No longer collected. Nobody owes it any more; what was already paid stays on
     * record with its receipt.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::CANCELLED;
    }

    public function isPostponed(): bool
    {
        return $this->status === self::POSTPONED;
    }

    /** Fees still being collected — postponed ones included. */
    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), '!=', self::CANCELLED);
    }
}
