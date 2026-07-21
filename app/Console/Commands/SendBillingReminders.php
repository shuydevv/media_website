<?php

namespace App\Console\Commands;

use App\Models\CourseUser;
use App\Notifications\PaymentDueSoonNotification;
use Illuminate\Console\Command;

class SendBillingReminders extends Command
{
    protected $signature = 'billing:send-reminders';
    protected $description = 'Отправить напоминание об оплате студентам, у которых платёж наступает в ближайшие 2 дня';

    public function handle(): int
    {
        $rows = CourseUser::query()
            ->whereNotNull('billing_interval_days')
            ->whereNotNull('next_payment_due_at')
            ->whereNull('reminder_sent_at')
            ->where('status', 'active')
            ->whereBetween('next_payment_due_at', [now(), now()->addDays(2)])
            ->with(['user', 'course'])
            ->get();

        foreach ($rows as $pivot) {
            if (!$pivot->user || !$pivot->course) {
                continue;
            }

            $pivot->user->notify(new PaymentDueSoonNotification(
                $pivot->user->first_name ?? $pivot->user->name,
                $pivot->course->title,
                $pivot->next_payment_due_at
            ));

            $pivot->update(['reminder_sent_at' => now()]);
        }

        $this->info("Напоминаний отправлено: {$rows->count()}");

        return self::SUCCESS;
    }
}
