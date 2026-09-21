<?php

namespace App\Events;

use App\Models\ClubNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Tell a user's other open tabs and devices that their notifications were read, so
 * every Notifications badge drops at once instead of showing a stale count until the
 * next full page load. Carries the new unread count only.
 */
class NotificationsRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $userId) {}

    /** Best-effort: a Reverb outage must never fail the page that marked them read. */
    public static function notify(int $userId): void
    {
        try {
            broadcast(new static($userId));
        } catch (\Throwable $e) {
            Log::warning('Notification read broadcast failed: ' . $e->getMessage());
        }
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('notifications.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'notification.read';
    }

    public function broadcastWith(): array
    {
        ClubNotification::forgetUnreadCount($this->userId);

        return ['unread' => ClubNotification::unreadCountFor($this->userId)];
    }
}
