<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Jobs\Telegram\HandleTelegramUpdate;
use App\Service\Telegram\TelegramBots;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Входящие апдейты ботов (TelegramBots): /api/telegram/webhook/{bot}, без
 * {bot} — бот воронки (так он зарегистрирован исторически). Адрес
 * регистрируется командой `php artisan telegram:webhook {bot}`, вместе с
 * секретом этого бота, который Telegram потом присылает в заголовке
 * X-Telegram-Bot-Api-Secret-Token — без него любой мог бы слать сюда
 * поддельные апдейты от имени пользователей.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request, string $bot = TelegramBots::LESSON)
    {
        if (! TelegramBots::exists($bot)) {
            abort(404);
        }

        $secret = TelegramBots::webhookSecret($bot);
        if ($secret === '' || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            abort(403);
        }

        $update = $request->json()->all();
        $updateId = $update['update_id'] ?? null;

        // Telegram повторяет апдейт, если не дождался ответа, — второй раз
        // тот же update_id не обрабатываем. Нумерация у каждого бота своя.
        if ($updateId !== null && Cache::add("telegram-update-{$bot}-{$updateId}", true, now()->addDay())) {
            $scenario = TelegramBots::scenario($bot);
            if (! $scenario) {
                // Бот заведён, а сценарий ещё не подключён — 200, чтобы
                // Telegram не копил и не повторял апдейты.
                Log::info("Telegram: апдейт боту {$bot} без сценария пропущен", ['update_id' => $updateId]);
            } elseif (isset($update['callback_query'])) {
                // Нажатие кнопки — сразу, не через очередь: воркер забирает
                // задачи раз в ~3 с, а «Спасибо за оценку!» и «✅ Ваша оценка»
                // должны появиться мгновенно. Это пара коротких запросов к
                // Telegram; отправки потяжелее handleCallback сам кладёт в очередь.
                try {
                    $scenario->handleUpdate($update);
                } catch (\Throwable $e) {
                    Log::error("Telegram {$bot}: не удалось обработать нажатие кнопки", ['error' => $e->getMessage()]);
                }
            } else {
                HandleTelegramUpdate::dispatch($update, $bot)->onQueue(config('telegram_funnel.queue'));
            }
        }

        return response()->json(['ok' => true]);
    }
}
