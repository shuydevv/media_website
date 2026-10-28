<?php

namespace App\Http\Controllers\Admin\Telegram\Broadcasts;

use App\Http\Controllers\Controller;
use App\Models\TelegramBroadcast;
use App\Service\Telegram\Broadcast\BroadcastDesk;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Галочки на странице рассылки: here — исключить из этой рассылки (пока
 * не отправлена), all — из всех рассылок, return — вернуть (снять оба).
 * Карточка в теме «📣 Рассылка» пересчитывается сразу.
 */
class PeopleController extends Controller
{
    public function __invoke(Request $request, TelegramBroadcast $broadcast)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['here', 'all', 'return'])],
            'ids' => ['required', 'array', 'max:1000'],
            'ids.*' => ['integer'],
        ], ['ids.required' => 'Отметьте хотя бы одного человека.']);

        $ids = array_map('intval', $data['ids']);
        $desk = BroadcastDesk::make();

        if ($data['action'] === 'here' && ! $broadcast->isEditable()) {
            return back()->with('error', 'Рассылка уже отправлена — исключать из неё поздно.');
        }

        match ($data['action']) {
            'here' => $desk->setExcluded($broadcast, $ids, true),
            'all' => $desk->setOptOut($ids, true),
            'return' => $this->returnPeople($desk, $broadcast, $ids),
        };

        $n = count($ids);

        return back()->with('success', match ($data['action']) {
            'here' => "Исключено из этой рассылки: {$n}",
            'all' => "Исключено из всех рассылок: {$n}",
            'return' => "Возвращено: {$n}",
        });
    }

    private function returnPeople(BroadcastDesk $desk, TelegramBroadcast $broadcast, array $ids): void
    {
        $desk->setOptOut($ids, false);
        if ($broadcast->isEditable()) {
            $desk->setExcluded($broadcast, $ids, false);
        }
    }
}
