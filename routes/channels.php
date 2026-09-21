<?php

use Illuminate\Support\Facades\Broadcast;

/*
| Private channel for a user's in-system notifications. Only the channel owner
| may subscribe. $user is resolved from the Firebase session by the
| firebase.token middleware on the /broadcasting/auth route (see bootstrap/app.php).
*/
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return $user && (int) $user->id === (int) $userId;
});

/*
| Private-messaging signals for one user. Owner-only, like notifications. Payloads
| carry ids only — message text is fetched over HTTPS (see App\Events\ChatActivity).
*/
Broadcast::channel('chat.{userId}', function ($user, $userId) {
    return $user && (int) $user->id === (int) $userId;
});
