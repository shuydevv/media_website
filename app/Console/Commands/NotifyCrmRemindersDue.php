<?php

namespace App\Console\Commands;

use App\Models\CrmReminder;
use App\Notifications\CrmReminderDueNotification;
use Illuminate\Console\Command;

class NotifyCrmRemindersDue extends Command
{
    protected $signature = 'crm:notify-reminders-due';

    protected $description = 'Уведомить админов по email о наступивших напоминаниях в CRM';

    public function handle(): int
    {
        $reminders = CrmReminder::query()
            ->whereNull('notified_at')
            ->where('due_at', '<=', now()->toDateString())
            ->with(['user', 'creator'])
            ->get();

        $sent = 0;
        foreach ($reminders as $reminder) {
            if (! $reminder->creator || ! $reminder->user) {
                continue;
            }

            $reminder->creator->notify(new CrmReminderDueNotification($reminder));
            $reminder->update(['notified_at' => now()]);
            $sent++;
        }

        $this->info("Уведомлений о напоминаниях CRM отправлено: {$sent}");

        return self::SUCCESS;
    }
}
