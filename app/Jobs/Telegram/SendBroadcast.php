<?php

namespace App\Jobs\Telegram;

use App\Models\TelegramBroadcast;
use App\Service\Telegram\Broadcast\BroadcastSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Порция рассылки (BroadcastSender::runChunk) — следующую ставит сама.
 * Одна попытка: упавшую посреди порции подхватит сторож
 * telegram:broadcasts (BroadcastSender::recoverStuck), а повтор задачи
 * целиком ничего не дал бы — захваченные получатели уже не pending.
 */
class SendBroadcast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $broadcastId)
    {
    }

    public function handle(): void
    {
        $broadcast = TelegramBroadcast::find($this->broadcastId);
        if (! $broadcast || $broadcast->status !== TelegramBroadcast::STATUS_SENDING) {
            return;
        }

        $next = BroadcastSender::make()->runChunk($broadcast);
        if ($next !== null) {
            BroadcastSender::dispatchChunk($broadcast, $next);
        }
    }
}
