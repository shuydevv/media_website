<?php

namespace App\Jobs\Telegram;

use App\Models\TelegramContact;
use App\Service\Telegram\FunnelBot;
use App\Service\Telegram\TelegramApiException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Один шаг воронки (одна отправка в Telegram) — см. FunnelBot::runStep().
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

        try {
            FunnelBot::make()->runStep($contact, $this->step, $this->payload);
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
