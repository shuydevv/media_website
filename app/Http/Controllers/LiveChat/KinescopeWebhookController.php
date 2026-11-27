<?php

namespace App\Http\Controllers\LiveChat;

use App\Http\Controllers\Controller;
use App\Service\LiveChat\LiveSignals;
use Illuminate\Http\Request;

/**
 * Уведомления Kinescope об эфире → чат трансляции открывается/закрывается
 * сам. Подпись у уведомлений не проверена — секрет в адресе (как у Авито).
 * Отвечаем 200 всегда, кроме неверного секрета: иначе Kinescope будет
 * повторять уведомление, которое нам не нужно.
 */
class KinescopeWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, LiveSignals $signals)
    {
        $expected = (string) config('live_chat.kinescope.webhook_secret');
        abort_unless($expected !== '' && hash_equals($expected, $secret), 404);

        $signals->handleWebhook($request->json()->all() ?: $request->all());

        return response()->json(['ok' => true]);
    }
}
