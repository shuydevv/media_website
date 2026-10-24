<?php

namespace App\Jobs\Telegram;

use App\Models\TelegramContact;
use App\Service\Telegram\TelegramApiException;
use App\Service\Telegram\TelegramBots;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Один шаг воронки (одна отправка в Telegram) — см. BotScenario::runStep()
 * (у бота воронки — FunnelBot::runStep()).
 * Контакт передаётся id, а не моделью: отложенный шаг должен видеть
 * состояние на момент отправки (нажал ли кнопку, написал ли), а не на момент
 * постановки в очередь.
 */
class RunFunnelStep implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(
        public int $contactId,
        public string $step,
        public array $payload = [],
    ) {
    }

    public function backoff(): array
    {
        return [5, 30, 120, 600];
    }

    public function handle(): void
    {
        $contact = TelegramContact::find($this->contactId);
        if (! $contact) {
            return;
        }

        // Шаг выполняет сценарий бота этого контакта.
        $scenario = TelegramBots::scenario($contact->bot);
        if (! $scenario) {
            Log::warning("Telegram: шаг {$this->step} для бота {$contact->bot} без сценария пропущен", ['contact' => $contact->id]);

            return;
        }

        try {
            $scenario->runStep($contact, $this->step, $this->payload);
        } catch (TelegramApiException $e) {
            // 429 — Telegram сам говорит, через сколько можно повторить.
            if ($e->isRateLimited() && $e->retryAfter) {
                $this->release($e->retryAfter + 1);

                return;
            }
            throw $e;
        }
    }
}
