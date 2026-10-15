<?php

namespace App\Console\Commands;

use App\Jobs\Telegram\RunFunnelStep;
use App\Models\TelegramContact;
use App\Service\Telegram\FunnelBot;
use Illuminate\Console\Command;

/**
 * Отложенные сообщения бота воронки: напоминания, «Как вам урок?» после
 * досмотра, вопрос от Александра после оценки (поля *_due_at, см.
 * FunnelBot::DUE_STEPS). Не delayed job в очереди: часы и сутки в `jobs`
 * выглядели бы для queue:monitor-health как зависшая очередь, а отменить
 * сообщение — это просто не выполнить условие в момент отправки.
 *
 * Запуск раз в минуту; всё, что наступит в ближайшую минуту, ставится в
 * очередь с задержкой до точной секунды — паузы «3–5 минут, случайно в
 * секундах» не округляются до минуты.
 */
class SendTelegramReminders extends Command
{
    protected $signature = 'telegram:send-reminders';

    protected $description = 'Поставить в очередь отложенные сообщения бота воронки, время которых наступает';

    public function handle(): int
    {
        $horizon = now()->addMinute();
        $fields = array_keys(FunnelBot::DUE_STEPS);

        $due = TelegramContact::query()
            ->where(function ($q) use ($fields, $horizon) {
                foreach ($fields as $field) {
                    $q->orWhere($field, '<=', $horizon);
                }
            })
            ->get();

        foreach ($due as $contact) {
            foreach (FunnelBot::DUE_STEPS as $field => [$step, $payload]) {
                $at = $contact->{$field};
                if ($at === null || $at->gt($horizon)) {
                    continue;
                }

                // Обнуляем сразу, чтобы следующий прогон не поставил второй раз;
                // условия (не оценил, не писал…) проверит сам шаг.
                $contact->{$field} = null;
                $job = RunFunnelStep::dispatch($contact->id, $step, $payload)->onQueue(config('telegram_funnel.queue'));
                if ($at->isFuture()) {
                    $job->delay($at);
                }
            }
            $contact->save();
        }

        return self::SUCCESS;
    }
}
