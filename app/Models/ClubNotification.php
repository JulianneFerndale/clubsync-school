<?php

namespace App\Models;

use App\Events\NotificationCreated;
use App\Support\LiveBroadcast;
use App\Support\UnreadCounts;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

            LiveBroadcast::send(new NotificationCreated($notification), 'Notification');
        });
    }

    protected $fillable = [
        'recipient_id', 'sender_type', 'club_id',
        'title', 'body', 'is_read', 'action_url',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    /**
     * Where the notification leads, as a path on this site: "/member/activity?tab=fees".
     *
     * Links are made with route(), which writes in the address of whatever made them: a
     * queue worker uses APP_URL, a web request its own host. Kept whole, a link the
     * worker made for http://clubsyncing.test took a member browsing on
     * http://localhost:8000 to another site, where they had no session — the sign-in
     * page, as if they had been logged out. Every notification links inside ClubSync, so
     * the address is dropped going in and coming out (rows saved before this still carry
     * one), and the browser follows the path on the site it is actually on.
     */
    protected function actionUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => self::sitePath($value),
            set: fn (?string $value) => self::sitePath($value),
        );
    }

    /** "http://any-host/path?query#part" → "/path?query#part"; a path is left as it is. */
    public static function sitePath(?string $url): ?string
    {
        if ($url === null || ! preg_match('#^https?://#i', $url) || ($parts = parse_url($url)) === false) {
            return $url;
        }

        return ($parts['path'] ?? '/')
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }

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
