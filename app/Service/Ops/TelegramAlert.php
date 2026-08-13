<?php

namespace App\Service\Ops;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Алерты о проблемах инфраструктуры (упавшая очередь, накопившиеся
 * failed_jobs и т.п.) — намеренно НЕ через Mail/Notifications: то, что
 * сломалось, чаще всего и есть сама почта (см. инцидент с истёкшим тарифом
 * SMTP — если бы алерт о нём тоже шёл письмом, никто бы его не увидел).
 *
 * Отдельный бот/чат от того, что зашит в LeadController (тот — под заявки
 * с сайта, трогать не нужно) — настраивается через TELEGRAM_BOT_TOKEN/
 * TELEGRAM_CHAT_ID в .env (см. config/services.php).
 */
class TelegramAlert
{
    public static function send(string $message): bool
    {
        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (!$botToken || !$chatId) {
            Log::warning('TelegramAlert: TELEGRAM_BOT_TOKEN/TELEGRAM_CHAT_ID не настроены в .env, алерт ушёл только в лог', ['message' => $message]);

            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->connectTimeout(5)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                ]);

            if ($response->failed()) {
                Log::error('TelegramAlert: отправка не удалась', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'message' => $message,
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('TelegramAlert: исключение при отправке', [
                'error' => $e->getMessage(),
                'message' => $message,
            ]);

            return false;
        }
    }
}
