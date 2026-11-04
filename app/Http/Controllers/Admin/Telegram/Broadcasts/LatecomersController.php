<?php

namespace App\Http\Controllers\Admin\Telegram\Broadcasts;

use App\Http\Controllers\Controller;
use App\Models\TelegramBroadcast;
use App\Service\Telegram\Broadcast\BroadcastSender;
use Illuminate\Http\Request;

/**
 * Вкладка «Зашли позже» на странице рассылки: дослать её всем, кто не
 * получал (all), или отмеченным (ids) — BroadcastSender::sendToLatecomers.
 */
class LatecomersController extends Controller
{
    public function __invoke(Request $request, TelegramBroadcast $broadcast)
    {
        $data = $request->validate([
            'all' => ['nullable', 'boolean'],
            'ids' => ['required_without:all', 'array', 'max:1000'],
            'ids.*' => ['integer'],
        ], ['ids.required_without' => 'Отметьте хотя бы одного человека.']);

        if ($broadcast->status !== TelegramBroadcast::STATUS_DONE) {
            return back()->with('error', 'Дослать можно только отправленную рассылку.');
        }

        $n = BroadcastSender::make()->sendToLatecomers($broadcast, ! empty($data['all']) ? null : array_map('intval', $data['ids']));

        return redirect()->route('admin.telegram.broadcasts.show', $broadcast)
            ->with($n ? 'success' : 'error', $n ? "Досылаем: {$n}" : 'Досылать некому.');
    }
}
