<?php

namespace App\Console\Commands;

use App\Jobs\Telegram\RunFunnelStep;
use App\Models\TelegramContact;
use Illuminate\Console\Command;

/**
 * Напоминания «Получилось посмотреть урок?» (бот воронки). Время каждому
 * назначается при старте (ReminderSchedule, 22–26 ч в окне 10–21 МСК) и
 * лежит в telegram_contacts.reminder_due_at — не отложенной задачей в
 * очереди: сутки в `jobs` выглядели бы для queue:monitor-health как
 * зависшая очередь, а отменить напоминание — это просто обнулить поле.
 */
class SendTelegramReminders extends Command
{
    protected $signature = 'telegram:send-reminders';

    protected $description = 'Поставить в очередь напоминания бота воронки, время которых наступило';

    public function handle(): int
    {
        $due = TelegramContact::query()
            ->whereNotNull('reminder_due_at')
            ->where('reminder_due_at', '<=', now())
            ->get();

        foreach ($due as $contact) {
            // Обнуляем сразу, чтобы следующий прогон через минуту не поставил
            // его второй раз; условия (не нажал, не писал) проверит сам шаг.
            $contact->forceFill(['reminder_due_at' => null])->save();

            if ($contact->automationAllowed()) {
                RunFunnelStep::dispatch($contact->id, 'reminder')->onQueue(config('telegram_funnel.queue'));
            }
        }

        return self::SUCCESS;
    }
}
