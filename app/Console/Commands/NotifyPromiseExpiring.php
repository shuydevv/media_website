<?php

namespace App\Console\Commands;

use App\Models\CourseUser;
use App\Notifications\PromisedPaymentExpiringNotification;
use Illuminate\Console\Command;

class NotifyPromiseExpiring extends Command
{
    protected $signature = 'billing:notify-promise-expiring';
    protected $description = 'Уведомить студентов, у которых обещанный платёж истекает в ближайшие сутки';

    public function handle(): int
    {
        $rows = CourseUser::query()
            ->whereNotNull('promised_payment_expires_at')
            ->where('status', 'active')
            ->whereNull('promise_expiring_notified_at')
            ->whereBetween('promised_payment_expires_at', [now(), now()->addDay()])
            ->with(['user', 'course'])
            ->get();

        foreach ($rows as $pivot) {
            if (!$pivot->user || !$pivot->course) {
                continue;
            }

            $pivot->user->notify(new PromisedPaymentExpiringNotification($pivot->course, $pivot->promised_payment_expires_at));
            $pivot->update(['promise_expiring_notified_at' => now()]);
        }

        $this->info("Уведомлений об истечении обещанного платежа отправлено: {$rows->count()}");

        return self::SUCCESS;
    }
}
