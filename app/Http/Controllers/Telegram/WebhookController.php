<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Jobs\Telegram\HandleTelegramUpdate;
use App\Service\Telegram\FunnelBot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Входящие апдейты бота воронки. Адрес регистрируется командой
 * `php artisan telegram:webhook`, вместе с секретом, который Telegram потом
 * присылает в заголовке X-Telegram-Bot-Api-Secret-Token — без него любой
 * мог бы слать сюда поддельные апдейты от имени пользователей.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $secret = (string) config('services.telegram_funnel.webhook_secret');
        if ($secret === '' || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            abort(403);
        }

        $update = $request->json()->all();
        $updateId = $update['update_id'] ?? null;

        // Telegram повторяет апдейт, если не дождался ответа, — второй раз
        // тот же update_id не обрабатываем.
        if ($updateId !== null && Cache::add("telegram-update-{$updateId}", true, now()->addDay())) {
            if (isset($update['callback_query'])) {
                // Нажатие кнопки — сразу, не через очередь: воркер забирает
                // задачи раз в ~3 с, а «Спасибо за оценку!» и «✅ Ваша оценка»
                // должны появиться мгновенно. Это пара коротких запросов к
                // Telegram; отправки потяжелее handleCallback сам кладёт в очередь.
                try {
                    FunnelBot::make()->handleUpdate($update);
                } catch (\Throwable $e) {
                    Log::error('Telegram funnel: не удалось обработать нажатие кнопки', ['error' => $e->getMessage()]);
                }
            } else {
                HandleTelegramUpdate::dispatch($update)->onQueue(config('telegram_funnel.queue'));
            }
        }

        return response()->json(['ok' => true]);
    }
}
