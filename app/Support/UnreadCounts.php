<?php

namespace App\Support;

use App\Models\ChatMessage;
use App\Models\ClubNotification;
use Illuminate\Support\Facades\DB;

/**
 * The two unread badge counts (notifications and private messages), read together.
 *
 * Every layout shows both badges, and with a remote database each query is a full
 * round trip, so they are fetched in one statement and memoised for the request.
 * ClubNotification::unreadCountFor() and ChatMessage::unreadCountFor() read from here;
 * their forgetUnreadCount() methods clear the memo after a change.
 */
final class UnreadCounts
{
    public const NOTIFICATIONS = 'notification_unread_count.';
    public const CHAT = 'chat_unread_count.';

    public static function notifications(int $userId): int
    {
        return self::get(self::NOTIFICATIONS, $userId);
    }

    public static function chat(int $userId): int
    {
        return self::get(self::CHAT, $userId);
    }

    public static function forget(string $kind, int $userId): void
    {
        request()->attributes->remove($kind . $userId);
    }

    private static function get(string $kind, int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $attributes = request()->attributes;

        if (! $attributes->has($kind . $userId)) {
            $row = DB::query()
                ->selectSub(
                    ClubNotification::query()->selectRaw('count(*)')
                        ->where('recipient_id', $userId)
                        ->where('is_read', false),
                    'notifications'
                )
                ->selectSub(ChatMessage::unreadQuery($userId)->selectRaw('count(*)'), 'chat')
                ->first();

            $attributes->set(self::NOTIFICATIONS . $userId, (int) $row->notifications);
            $attributes->set(self::CHAT . $userId, (int) $row->chat);
        }

        return $attributes->get($kind . $userId);
    }
}
