<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A private, one-to-one conversation between two users.
 *
 * Only the two participants may ever read it (POLICY.md §Private Messaging). There
 * is deliberately no role — DSA or admin included — that can open someone else's
 * conversation, and the controller answers a non-participant with 404 so that even
 * the existence of a conversation is not disclosed.
 */
class Conversation extends Model
{
    protected $fillable = ['user_low_id', 'user_high_id', 'last_message_id', 'last_message_at'];

    protected $casts = [
        'user_low_id'     => 'integer',
        'user_high_id'    => 'integer',
        'last_message_id' => 'integer',
        'last_message_at' => 'datetime',
    ];

    public function userLow(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_low_id');
    }

    public function userHigh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_high_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'last_message_id');
    }

    /** Conversations the given user takes part in. */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(fn ($q) => $q->where('user_low_id', $userId)->orWhere('user_high_id', $userId));
    }

    /**
     * The conversation between two users, created (with both participant rows) on
     * first use. Safe against two people opening the same thread at once.
     */
    public static function between(User $a, User $b): self
    {
        [$low, $high] = $a->id < $b->id ? [$a->id, $b->id] : [$b->id, $a->id];

        return DB::transaction(function () use ($low, $high) {
            $conversation = static::createOrFirst(['user_low_id' => $low, 'user_high_id' => $high]);

            foreach ([$low, $high] as $userId) {
                ConversationParticipant::createOrFirst([
                    'conversation_id' => $conversation->id,
                    'user_id'         => $userId,
                ]);
            }

            return $conversation;
        });
    }

    public function hasParticipant(int $userId): bool
    {
        return $userId > 0 && ($this->user_low_id === $userId || $this->user_high_id === $userId);
    }

    public function otherUserId(int $userId): int
    {
        return $this->user_low_id === $userId ? $this->user_high_id : $this->user_low_id;
    }

    public function otherUser(int $userId): ?User
    {
        return $this->user_low_id === $userId ? $this->userHigh : $this->userLow;
    }

    public function participantFor(int $userId): ConversationParticipant
    {
        return $this->participants()->firstOrCreate(['user_id' => $userId]);
    }

    /** Messages this user can still see — everything after the point they cleared the thread. */
    public function visibleMessagesFor(ConversationParticipant $participant)
    {
        return $this->messages()
            ->when($participant->cleared_through_message_id, fn ($q, $id) => $q->where('id', '>', $id))
            ->orderBy('id');
    }

    /**
     * Permanently delete messages that no one can see any more.
     *
     * Once both participants have cleared a stretch of history, keeping it would be
     * storing personal data that serves no purpose (RA 10173 §11 — proportionality).
     * If nothing is left, the conversation itself is removed.
     *
     * @return int  number of messages deleted
     */
    public function purgeUnreachableMessages(): int
    {
        $markers = $this->participants()->pluck('cleared_through_message_id');

        // A participant who never cleared still sees everything.
        if ($markers->count() < 2 || $markers->contains(null)) {
            return 0;
        }

        $deleted = $this->messages()->where('id', '<=', $markers->min())->delete();

        if (! $this->messages()->exists()) {
            $this->delete();
        }

        return $deleted;
    }
}
