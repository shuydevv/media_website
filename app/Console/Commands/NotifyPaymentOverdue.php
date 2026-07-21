<?php

namespace App\Console\Commands;

use App\Models\CourseUser;
use App\Notifications\PaymentOverdueNotification;
use App\Service\BillingService;
use Illuminate\Console\Command;

class NotifyPaymentOverdue extends Command
{
    protected $signature = 'billing:notify-overdue';
    protected $description = 'Уведомить студентов, у которых доступ к курсу приостановлен из-за просрочки оплаты';

    public function handle(BillingService $billing): int
    {
        $rows = CourseUser::query()
            ->whereNotNull('billing_interval_days')
            ->where('status', 'active')
            ->whereNull('overdue_notified_at')
            ->with(['user', 'course'])
            ->get()
            // Не просто "просрочен день оплаты" — а реально сейчас нет доступа:
            // если действует обещанный платёж, hasAccess() всё ещё true, слать рано.
            ->filter(fn (CourseUser $pivot) => $pivot->user && $pivot->course
                && !$billing->hasAccess($pivot->user, $pivot->course));

        foreach ($rows as $pivot) {
            $pivot->user->notify(new PaymentOverdueNotification($pivot->course));
            $pivot->update(['overdue_notified_at' => now()]);
        }

        $this->info("Уведомлений о просрочке отправлено: {$rows->count()}");

        return self::SUCCESS;
    }
}
