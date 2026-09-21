<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\NotificationController as BaseNotificationController;

/** The same notifications, rendered inside the member chrome (member.notifications.*). */
class NotificationController extends BaseNotificationController
{
    protected function view(): string
    {
        return 'member.notifications.index';
    }
}
