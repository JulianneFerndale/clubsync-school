<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Tell one user that something changed in one of their conversations.
 *
 * The payload is a signal, not the message: it carries ids and an unread count but
 * never the text or the sender's name. The browser fetches the message itself over
 * the authenticated HTTPS endpoint. This keeps private text out of the WebSocket
 * server, out of the `log` broadcaster (which writes payloads to laravel.log) and
 * off a shared screen via the toast.
 *
 * Types: message | retracted (a participant's message changed), read (this user read a
 * conversation on one tab or device, so the others can clear their badge) and access
 * (a block between the two participants was added or lifted).
 */
class ChatActivity implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $userId,
        public string $type,
        public int $conversationId,
        public int $messageId,
        public int $senderId,
    ) {}

    /** Broadcast to each user, best-effort: a Reverb outage must never fail the request. */
    public static function notify(array $userIds, string $type, ChatMessage $message): void
    {
        foreach (array_unique($userIds) as $userId) {
            static::send(new static((int) $userId, $type, $message->conversation_id, $message->id, $message->sender_id));
        }
    }

    /** This user's unread count dropped because they read a conversation. */
    public static function read(int $userId, int $conversationId): void
    {
        static::send(new static($userId, 'read', $conversationId, 0, 0));
    }

    /** Whether this user may send in a conversation changed (a block was added or lifted). */
    public static function access(int $userId, int $conversationId): void
    {
        static::send(new static($userId, 'access', $conversationId, 0, 0));
    }

    private static function send(self $event): void
    {
        try {
            broadcast($event);
        } catch (\Throwable $e) {
            Log::warning('Chat broadcast failed: ' . $e->getMessage());
        }
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'chat.activity';
    }

    public function broadcastWith(): array
    {
        // A sender's own message or unsend, or a block change, never changes the user's
        // unread count, so skip that query (null tells the browser to leave the badge as it is).
        $unread = null;
        if ($this->userId !== $this->senderId && $this->type !== 'access') {
            ChatMessage::forgetUnreadCount($this->userId);
            $unread = ChatMessage::unreadCountFor($this->userId);
        }

        return [
            'type'            => $this->type,
            'conversation_id' => $this->conversationId,
            'message_id'      => $this->messageId,
            'sender_id'       => $this->senderId,
            'unread'          => $unread,
        ];
    }
}
