<?php

namespace App\Http\Controllers;

use App\Events\NotificationsRead;
use App\Models\ChatMessage;
use App\Models\ClubNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * In-system notifications for every role (POLICY.md §Notifications: the in-app
 * notification is the system of record, so each role needs a page to read it).
 */
class NotificationController extends Controller
{
    public function index(): View
    {
        $userId = auth_user_id();

        $unreadCount = ClubNotification::unreadCountFor($userId);

        $notifications = ClubNotification::where('recipient_id', $userId)
            ->with('club')
            ->orderByDesc('created_at')
            ->paginate(20);

        // Mark all as read after counting and fetching so the page reflects unread state.
        if ($unreadCount > 0) {
            ClubNotification::where('recipient_id', $userId)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            ClubNotification::forgetUnreadCount($userId); // the layout badge must now show 0
            NotificationsRead::notify($userId);
        }

        return view($this->view(), [
            'layout'        => role_layout(auth()->user()?->role),
            'notifications' => $notifications,
            'unreadCount'   => $unreadCount,
        ]);
    }

    public function markRead(ClubNotification $notification): RedirectResponse
    {
        abort_if($notification->recipient_id !== auth_user_id(), 403);

        if (! $notification->is_read) {
            $notification->update(['is_read' => true]);
            ClubNotification::forgetUnreadCount($notification->recipient_id);
            NotificationsRead::notify($notification->recipient_id);
        }

        return redirect($notification->action_url ?? route('notifications.index'));
    }

    /** Current badge counts, so a page restored from cache or reconnected can catch up. */
    public function counts(): JsonResponse
    {
        $userId = auth_user_id();

        return response()->json([
            'notifications' => ClubNotification::unreadCountFor($userId),
            'chat'          => ChatMessage::unreadCountFor($userId),
        ]);
    }

    protected function view(): string
    {
        return 'notifications.index';
    }
}
