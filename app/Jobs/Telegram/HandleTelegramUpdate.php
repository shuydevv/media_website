<?php

namespace App\Jobs\Telegram;

use App\Service\Telegram\FunnelBot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Апдейт от Telegram, принятый webhook-ом (Telegram\WebhookController).
 * Разбор — в очереди, а не в самом запросе: Telegram ждёт быстрый 200 и
 * иначе шлёт тот же апдейт повторно.
 *
 * Одна попытка: обработчик пишет в БД и раскладывает отправки по задачам
 * RunFunnelStep (у тех свои повторы). Повтор всего апдейта создал бы дубли
 * этих задач — например, второе приветствие.
 */
class HandleTelegramUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public array $update)
    {
    }

    public function handle(): void
    {
        FunnelBot::make()->handleUpdate($this->update);
    }
}
