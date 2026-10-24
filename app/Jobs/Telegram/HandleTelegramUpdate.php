<?php

namespace App\Jobs\Telegram;

use App\Service\Telegram\TelegramBots;
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

    /**
     * Чей апдейт (TelegramBots). Значение по умолчанию — у свойства, а не
     * только у конструктора: задачи, поставленные до появления поля,
     * распаковываются без конструктора и должны остаться ботом воронки.
     */
    public string $bot = TelegramBots::LESSON;

    public function __construct(public array $update, string $bot = TelegramBots::LESSON)
    {
        $this->bot = $bot;
    }

    public function handle(): void
    {
        TelegramBots::scenario($this->bot)?->handleUpdate($this->update);
    }
}
