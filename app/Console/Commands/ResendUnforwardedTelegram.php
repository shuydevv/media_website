<?php

namespace App\Console\Commands;

use App\Models\TelegramMessage;
use App\Service\Ops\TelegramAlert;
use App\Service\Telegram\AdminTopics;
use App\Service\Telegram\TelegramBots;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Страховка пересылки сообщений человека в его тему админ-группы. Обычно
 * их пересылает задача очереди forward сразу; если она исчерпала попытки
 * (кэш, права бота в группе, Telegram недоступен) или очередь стоит —
 * сообщение без forwarded_at досылается отсюда, раз в 5 минут, пока не
 * получится. С третьей попытки — текстом из базы (forwardAsText): если
 * человек удалил сообщение, forwardMessage не пройдёт уже никогда.
 *
 * После первой неудачной досылки — один алерт со звуком в «⚠️ Сбои» и
 * TelegramAlert: молча копить недоставленное нельзя.
 *
 * Без withoutOverlapping (его мьютекс — тот же файловый кэш): два запуска
 * разом не перешлют одно сообщение, потому что каждое сначала «берётся»
 * условным UPDATE forward_claimed_at.
 */
class ResendUnforwardedTelegram extends Command
{
    protected $signature = 'telegram:resend-unforwarded';

    protected $description = 'Дослать в темы админ-группы сообщения людей, которые не переслались';

    private const RETRY_MINUTES = 5;

    private const TEXT_FALLBACK_AFTER_FAILURES = 2;

    public function handle(): int
    {
        if (! AdminTopics::adminChatId()) {
            return self::SUCCESS;
        }

        $base = fn () => TelegramMessage::query()
            ->where('kind', TelegramMessage::KIND_USER)
            ->whereNull('forwarded_at')
            ->whereNotNull('telegram_message_id')
            ->where('created_at', '<=', now()->subMinutes(config('telegram_funnel.timing.forward_resend_after_minutes')))
            // Старше недели — уже не досылаем: в теме это никому не поможет.
            ->where('created_at', '>=', now()->subDays(7))
            ->where(fn ($q) => $q->whereNull('forward_claimed_at')->orWhere('forward_claimed_at', '<=', now()->subMinutes(self::RETRY_MINUTES)));

        foreach ($base()->with('contact')->orderBy('id')->limit(20)->get() as $message) {
            if (! $base()->whereKey($message->id)->update(['forward_claimed_at' => now()])) {
                continue; // взял параллельный запуск
            }
            $contact = $message->contact;
            $topics = new AdminTopics(TelegramBots::api($contact->bot));

            try {
                $message->forward_failures >= self::TEXT_FALLBACK_AFTER_FAILURES
                    ? $topics->forwardAsText($contact, $message)
                    : $topics->forward($contact, (int) $message->telegram_message_id);
                $topics->refreshStatus($contact);
            } catch (\Throwable $e) {
                $message->forceFill([
                    'forward_failures' => $message->forward_failures + 1,
                    'forward_error' => mb_substr($e->getMessage(), 0, 255),
                ])->save();
                Log::warning('Telegram: не удалось дослать сообщение в тему', ['message' => $message->id, 'error' => $e->getMessage()]);
            }

            $message->refresh();
            if (! $message->forwarded_at && ! $message->forward_alerted_at) {
                $this->alertUndelivered($message, $topics);
            }
        }

        return self::SUCCESS;
    }

    private function alertUndelivered(TelegramMessage $message, AdminTopics $topics): void
    {
        $contact = $message->contact;
        $what = 'Сообщение от '.$contact->displayName().' ('.$message->created_at->format('d.m H:i').') не попало в его тему';
        $why = $message->forward_error ? 'Ошибка: '.$message->forward_error : 'Тема не создаётся — проверьте права бота в группе';
        $link = route('admin.telegram.contacts.show', $contact);

        // Бот этого контакта — точно с рабочим токеном (он же принял
        // сообщение); но если сломана сама группа, дойдёт только TelegramAlert.
        try {
            $topics->postToService('alerts', '⚠️ <b>'.e($what).'</b>'."\n".e($why)."\n".'Досылаю раз в '.self::RETRY_MINUTES.' мин. Текст — в <a href="'.e($link).'">карточке</a>.', false);
        } catch (\Throwable $e) {
            Log::warning('Telegram: не удалось написать в «Сбои»', ['error' => $e->getMessage()]);
        }
        TelegramAlert::send("⚠️ {$what}. {$why}. {$link}");

        $message->forceFill(['forward_alerted_at' => now()])->save();
    }
}
