<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Jobs\Telegram\HandleTelegramUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
            HandleTelegramUpdate::dispatch($update)->onQueue(config('telegram_funnel.queue'));
        }

        return response()->json(['ok' => true]);
    }
}
