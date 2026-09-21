<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationParticipant extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'last_read_message_id', 'cleared_through_message_id'];

    protected $casts = [
        'last_read_message_id'       => 'integer',
        'cleared_through_message_id' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Move the read marker forward (never back). */
    /** @return bool whether the read marker moved (i.e. the unread count may have dropped) */
    public function markReadThrough(?int $messageId): bool
    {
        if ($messageId && $messageId > (int) $this->last_read_message_id) {
            $this->update(['last_read_message_id' => $messageId]);

            return true;
        }

        return false;
    }
}
