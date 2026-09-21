<?php

namespace App\Models;

use App\Events\NotificationCreated;
use App\Support\UnreadCounts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class ClubNotification extends Model
{
    protected $table = 'notifications';

    /**
     * Push every newly-created notification to its recipient in real time.
     * Broadcasting is best-effort: a delivery/connection failure (e.g. the Reverb
     * server is down) is logged but never blocks persisting the notification.
     */
    protected static function booted(): void
    {
        static::created(function (ClubNotification $notification) {
            if (! $notification->recipient_id) {
                return;
            }

            // A queue worker keeps one request object across jobs; never reuse a stale count.
            static::forgetUnreadCount($notification->recipient_id);

            try {
                broadcast(new NotificationCreated($notification));
            } catch (\Throwable $e) {
                Log::warning('Notification broadcast failed: ' . $e->getMessage());
            }
        });
    }

    protected $fillable = [
        'recipient_id', 'sender_type', 'club_id',
        'title', 'body', 'is_read', 'action_url',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * Unread count for the badge. Memoised for the request (the layout, the bottom nav and
     * the page itself each ask) and read together with the chat count (see UnreadCounts).
     */
    public static function unreadCountFor(int $userId): int
    {
        return UnreadCounts::notifications($userId);
    }

    /** Drop the memoised count after this request changes it. */
    public static function forgetUnreadCount(int $userId): void
    {
        UnreadCounts::forget(UnreadCounts::NOTIFICATIONS, $userId);
    }
}
