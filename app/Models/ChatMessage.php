<?php

namespace App\Models;

use App\Support\UnreadCounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One private message.
 *
 * `body` is encrypted at rest (POLICY.md §Data Privacy: all PII must be encrypted at
 * rest) — the database, its backups and the Supabase dashboard only ever see
 * ciphertext. Message text must never be copied anywhere else in plaintext: not
 * into notifications, broadcasts, logs or the audit log.
 */
class ChatMessage extends Model
{
    protected $fillable = ['conversation_id', 'sender_id', 'body', 'retracted_at'];

    protected $casts = [
        'conversation_id' => 'integer',
        'sender_id'       => 'integer',
        'body'            => 'encrypted',
        'retracted_at'    => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function isRetracted(): bool
    {
        return $this->retracted_at !== null;
    }

    /** "Unsend": the text is destroyed, only a "message deleted" placeholder remains. */
    public function retract(): void
    {
        $this->update(['body' => null, 'retracted_at' => now()]);
    }

    /** The shape the chat page renders. Only ever sent to a participant. */
    public function toClientArray(int $viewerId): array
    {
        return [
            'id'        => $this->id,
            'mine'      => $this->sender_id === $viewerId,
            'body'      => $this->isRetracted() ? null : $this->body,
            'retracted' => $this->isRetracted(),
            'sent_at'   => $this->created_at?->toIso8601String(),
            'time'      => $this->created_at?->format('M j, g:i A'),
        ];
    }

    /**
     * Unread messages across all of a user's conversations: messages from the other
     * person, newer than both their read marker and the point they cleared the thread.
     * Memoised per request, and read together with the notification count (see UnreadCounts).
     */
    public static function unreadCountFor(int $userId): int
    {
        return UnreadCounts::chat($userId);
    }

    /** The unread-message rows for a user, uncounted (UnreadCounts wraps it in count(*)). */
    public static function unreadQuery(int $userId): Builder
    {
        return static::query()
            ->join('conversation_participants as cp', function ($join) use ($userId) {
                $join->on('cp.conversation_id', '=', 'chat_messages.conversation_id')
                    ->where('cp.user_id', '=', $userId);
            })
            ->where('chat_messages.sender_id', '!=', $userId)
            ->whereNull('chat_messages.retracted_at')
            ->whereRaw('chat_messages.id > COALESCE(cp.last_read_message_id, 0)')
            ->whereRaw('chat_messages.id > COALESCE(cp.cleared_through_message_id, 0)');
    }

    /** Drop the memoised badge count after this request changes it. */
    public static function forgetUnreadCount(int $userId): void
    {
        UnreadCounts::forget(UnreadCounts::CHAT, $userId);
    }
}
