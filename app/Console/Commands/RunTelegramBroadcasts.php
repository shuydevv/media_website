<?php

namespace App\Console\Commands;

use App\Models\TelegramBroadcast;
use App\Service\Telegram\Broadcast\BroadcastSender;
use Illuminate\Console\Command;

/**
 * Раз в минуту: запланированные рассылки, время которых пришло, — в
 * отправку; отправка без движения (упала задача SendBroadcast) — порцию
 * заново (BroadcastSender::recoverStuck). Как и telegram:send-reminders —
 * не delayed job: план на сутки в `jobs` выглядел бы зависшей очередью, а
 * снять с плана — это просто сменить статус.
 */
class RunTelegramBroadcasts extends Command
{
    protected $signature = 'telegram:broadcasts';

    protected $description = 'Запустить запланированные рассылки и подхватить зависшие';

    public function handle(): int
    {
        $sender = BroadcastSender::make();
        $due = TelegramBroadcast::where('status', TelegramBroadcast::STATUS_SCHEDULED)->where('scheduled_at', '<=', now())->get();
        foreach ($due as $broadcast) {
            $sender->start($broadcast);
        }

        BroadcastSender::recoverStuck();

        return self::SUCCESS;
    }
}
