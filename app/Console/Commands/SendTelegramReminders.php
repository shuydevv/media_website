<?php

namespace App\Console\Commands;

use App\Jobs\Telegram\RunFunnelStep;
use App\Models\TelegramContact;
use App\Service\Telegram\TelegramBots;
use Illuminate\Console\Command;

/**
 * Отложенные сообщения ботов: напоминания, «Как вам урок?» после досмотра,
 * вопрос от Александра после оценки — поля *_due_at контакта; у каждого
 * сценария свои (BotScenario::dueSteps(), у бота воронки —
 * FunnelBot::DUE_STEPS). Не delayed job в очереди: часы и сутки в `jobs`
 * выглядели бы для queue:monitor-health как зависшая очередь, а отменить
 * сообщение — это просто не выполнить условие в момент отправки.
 *
 * Запуск раз в минуту; всё, что наступит в ближайшую минуту, ставится в
 * очередь с задержкой до точной секунды — паузы «1–4 минуты, случайно в
 * секундах» не округляются до минуты.
 */
class SendTelegramReminders extends Command
{
    protected $signature = 'telegram:send-reminders';

    protected $description = 'Поставить в очередь отложенные сообщения бота воронки, время которых наступает';

    public function handle(): int
    {
        foreach (TelegramBots::scenarios() as $bot => $scenario) {
            $this->dispatchDue($bot, $scenario->dueSteps());
        }

        return self::SUCCESS;
    }

    /** @param  array<string, array{0: string, 1: array}>  $steps  поле *_due_at → [шаг, payload] */
    private function dispatchDue(string $bot, array $steps): void
    {
        $horizon = now()->addMinute();
        $fields = array_keys($steps);

        $due = TelegramContact::forBot($bot)
            ->where(function ($q) use ($fields, $horizon) {
                foreach ($fields as $field) {
                    $q->orWhere($field, '<=', $horizon);
                }
            })
            ->get();

        foreach ($due as $contact) {
            foreach ($steps as $field => [$step, $payload]) {
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
    }
}
