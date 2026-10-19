<?php

namespace App\Console\Commands;

use App\Models\TelegramContact;
use App\Service\Telegram\AdminTopics;
use App\Service\Telegram\TelegramApi;
use Illuminate\Console\Command;

/**
 * Напоминание админу со звуком в теме человека: написал и ждёт ответа
 * дольше unanswered_alert_minutes (2 ч). Одно на каждое оставшееся без
 * ответа сообщение; только днём (окно 10–21) — ночное сообщение
 * напомнит утром. Статус 💬 в названии темы и строка в вечерней сводке
 * есть и так, но их легко пропустить.
 */
class RemindUnansweredTelegram extends Command
{
    protected $signature = 'telegram:remind-unanswered';

    protected $description = 'Напомнить в теме админ-группы о тех, кто ждёт ответа дольше 2 часов';

    public function handle(): int
    {
        $t = config('telegram_funnel.timing');
        $hour = (int) now()->format('G');
        if ($hour < $t['window_start_hour'] || $hour >= $t['window_end_hour']) {
            return self::SUCCESS;
        }

        $topics = new AdminTopics(TelegramApi::funnel());
        $contacts = TelegramContact::query()
            ->whereNull('blocked_at')
            ->whereNotNull('topic_id')
            ->whereNotNull('last_user_message_at')
            ->where('last_user_message_at', '<=', now()->subMinutes($t['unanswered_alert_minutes']))
            ->where(fn ($q) => $q->whereNull('last_admin_message_at')->orWhereColumn('last_admin_message_at', '<', 'last_user_message_at'))
            ->where(fn ($q) => $q->whereNull('awaiting_alerted_at')->orWhereColumn('awaiting_alerted_at', '<', 'last_user_message_at'))
            ->get();

        foreach ($contacts as $contact) {
            $hours = $contact->last_user_message_at->diffInHours(now());
            $topics->note($contact, '⏰ <b>Ждёт ответа уже '.($hours >= 1 ? $hours.' ч' : 'больше часа').'</b> — последнее сообщение в '
                .$contact->last_user_message_at->format('d.m H:i'), false);
            $contact->forceFill(['awaiting_alerted_at' => now()])->save();
        }

        return self::SUCCESS;
    }
}
