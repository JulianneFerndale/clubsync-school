<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One user refusing private messages from another. Either direction stops both sides sending. */
class ChatBlock extends Model
{
    protected $fillable = ['blocker_id', 'blocked_id'];

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }

    public static function existsBetween(int $a, int $b): bool
    {
        return static::query()
            ->where(fn ($q) => $q->where('blocker_id', $a)->where('blocked_id', $b))
            ->orWhere(fn ($q) => $q->where('blocker_id', $b)->where('blocked_id', $a))
            ->exists();
    }

    /**
     * Who has blocked whom between two users, in one query (0, 1 or 2 blocker ids).
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public static function blockersBetween(int $a, int $b)
    {
        return static::query()
            ->where(fn ($q) => $q->where('blocker_id', $a)->where('blocked_id', $b))
            ->orWhere(fn ($q) => $q->where('blocker_id', $b)->where('blocked_id', $a))
            ->pluck('blocker_id')
            ->map(fn ($id) => (int) $id);
    }

    public static function isBlockedBy(int $blockerId, int $blockedId): bool
    {
        return static::where('blocker_id', $blockerId)->where('blocked_id', $blockedId)->exists();
    }
}
