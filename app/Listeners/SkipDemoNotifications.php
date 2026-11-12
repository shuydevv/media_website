<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Notifications\Events\NotificationSending;

/**
 * Демо-ученику (показ курса) ничего не отправляем: ни писем о зачислении
 * и оплате, ни напоминаний об уроках и домашках — адрес у него ненастоящий.
 * false из слушателя NotificationSending отменяет отправку.
 */
class SkipDemoNotifications
{
    public function handle(NotificationSending $event): bool
    {
        return ! ($event->notifiable instanceof User && $event->notifiable->is_demo);
    }
}
