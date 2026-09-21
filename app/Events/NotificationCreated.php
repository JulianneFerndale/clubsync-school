<?php

namespace App\Events;

use App\Models\ClubNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast a freshly-created in-system notification to its recipient in real time.
 *
 * Sent on the recipient's private channel so only they receive it. Uses
 * ShouldBroadcastNow so the push is immediate and does not depend on a broadcast
 * queue worker (many notifications are already created inside queued jobs).
 */
class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ClubNotification $notification) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('notifications.' . $this->notification->recipient_id);
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id'         => $this->notification->id,
            'club_id'    => $this->notification->club_id,
            'title'      => $this->notification->title,
            'body'       => $this->notification->body,
            'action_url' => $this->notification->action_url,
            'created_at' => optional($this->notification->created_at)->toIso8601String(),
            'unread'     => ClubNotification::unreadCountFor($this->notification->recipient_id),
        ];
    }
}
