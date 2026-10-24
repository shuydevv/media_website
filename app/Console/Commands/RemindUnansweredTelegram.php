<?php

namespace App\Console\Commands;

use App\Models\TelegramContact;
use App\Service\Telegram\AdminTopics;
use App\Service\Telegram\TelegramBots;
use Illuminate\Console\Command;

/**
 * Напоминание админу со звуком в теме человека: написал и ждёт ответа
 * дольше unanswered_alert_minutes (бот урока — 2 ч, бот диагностики —
 * 15 мин, config/telegram_diag.php: лид горячий). Одно на каждое оставшееся без
 * ответа сообщение; только днём (окно 10–21) — ночное сообщение
 * напомнит утром. Статус 💬 в названии темы и строка в вечерней сводке
 * есть и так, но их легко пропустить.
 */
class RemindUnansweredTelegram extends Command
{
    protected $signature = 'telegram:remind-unanswered';

    protected $description = 'Напомнить в теме админ-группы о тех, кто ждёт ответа (урок — 2 ч, диагностика — 15 мин)';

    public function handle(): int
    {
        $t = config('telegram_funnel.timing');
        $hour = (int) now()->format('G');
        if ($hour < $t['window_start_hour'] || $hour >= $t['window_end_hour']) {
            return self::SUCCESS;
        }

        $minutes = fn (string $bot) => (int) ($bot === TelegramBots::DIAG
            ? config('telegram_diag.timing.unanswered_alert_minutes', $t['unanswered_alert_minutes'])
            : $t['unanswered_alert_minutes']);
        $shortest = min($minutes(TelegramBots::LESSON), $minutes(TelegramBots::DIAG));

        $contacts = TelegramContact::query()
            ->whereNull('blocked_at')
            ->whereNotNull('topic_id')
            ->whereNotNull('last_user_message_at')
            ->where('last_user_message_at', '<=', now()->subMinutes($shortest))
            ->where(fn ($q) => $q->whereNull('last_admin_message_at')->orWhereColumn('last_admin_message_at', '<', 'last_user_message_at'))
            ->where(fn ($q) => $q->whereNull('awaiting_alerted_at')->orWhereColumn('awaiting_alerted_at', '<', 'last_user_message_at'))
            ->get()
            ->filter(fn ($c) => $c->last_user_message_at->lte(now()->subMinutes($minutes($c->bot))));

        foreach ($contacts as $contact) {
            $waited = (int) $contact->last_user_message_at->diffInMinutes(now());
            // В теме пишет бот этого человека — тему создавал он.
            (new AdminTopics(TelegramBots::api($contact->bot)))->note($contact, '⏰ <b>Ждёт ответа уже '.($waited >= 60 ? intdiv($waited, 60).' ч' : $waited.' мин').'</b> — последнее сообщение в '
                .$contact->last_user_message_at->format('d.m H:i'), false);
            $contact->forceFill(['awaiting_alerted_at' => now()])->save();
        }

        return self::SUCCESS;
    }
}
