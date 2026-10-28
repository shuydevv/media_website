<?php

namespace App\Http\Controllers\Admin\Telegram\Broadcasts;

use App\Http\Controllers\Controller;
use App\Models\TelegramBroadcast;
use App\Models\TelegramBroadcastRecipient;
use App\Models\TelegramContact;
use App\Service\Telegram\Broadcast\BroadcastAudience;
use App\Service\Telegram\Broadcast\BroadcastDesk;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Рассылка: что уходит и кому. Пока не отправлена — получатели считаются
 * заново (BroadcastAudience), вкладки «Получат» / «Не получат» и галочки:
 * исключить из этой рассылки, из всех или вернуть (PeopleController). После
 * старта — зафиксированные получатели со статусом доставки.
 */
class ShowController extends Controller
{
    private const TABS_EDITABLE = ['in' => 'Получат', 'out' => 'Не получат'];

    private const TABS_SENT = ['all' => 'Все', 'sent' => 'Доставлено', 'failed' => 'Не доставлено', 'replied' => 'Ответили'];

    public function __invoke(Request $request, TelegramBroadcast $broadcast)
    {
        $q = trim($request->string('q')->toString());
        $editable = $broadcast->isEditable();
        $tabs = $editable ? self::TABS_EDITABLE : self::TABS_SENT;
        $tab = $request->string('tab')->toString();
        if (! array_key_exists($tab, $tabs)) {
            $tab = array_key_first($tabs);
        }

        // Строки: ['contact' => TelegramContact, 'note' => причина/статус, 'tone' => цвет, 'recipient' => ?строка получателя]
        if ($editable) {
            $a = BroadcastAudience::for($broadcast);
            $groups = [
                'in' => $a->recipients->map(fn (TelegramContact $c) => ['contact' => $c, 'note' => null, 'tone' => null, 'recipient' => null]),
                'out' => $a->excluded->map(fn ($e) => ['contact' => $e['contact'], 'note' => BroadcastAudience::REASON_LABELS[$e['reason']],
                    'tone' => $e['reason'] === BroadcastAudience::REASON_BLOCKED ? 'red' : 'orange', 'reason' => $e['reason'], 'recipient' => null]),
            ];
            $stats = null;
        } else {
            $all = $broadcast->recipients()->with('contact')->orderBy('id')->get()->map(fn (TelegramBroadcastRecipient $r) => [
                'contact' => $r->contact, 'recipient' => $r,
                'note' => match ($r->status) {
                    TelegramBroadcastRecipient::STATUS_SENT => 'доставлено',
                    TelegramBroadcastRecipient::STATUS_BLOCKED => 'заблокировал бота',
                    TelegramBroadcastRecipient::STATUS_FAILED => 'ошибка',
                    default => 'не отправлено',
                },
                'tone' => match ($r->status) {
                    TelegramBroadcastRecipient::STATUS_SENT => 'green',
                    TelegramBroadcastRecipient::STATUS_BLOCKED, TelegramBroadcastRecipient::STATUS_FAILED => 'red',
                    default => 'gray',
                },
            ]);
            $groups = [
                'all' => $all,
                'sent' => $all->filter(fn ($row) => $row['recipient']->status === TelegramBroadcastRecipient::STATUS_SENT),
                'failed' => $all->filter(fn ($row) => $row['recipient']->status !== TelegramBroadcastRecipient::STATUS_SENT),
                'replied' => $all->filter(fn ($row) => $row['recipient']->replied_at !== null),
            ];
            $stats = BroadcastDesk::make()->stats($broadcast);
        }

        $search = function ($rows) use ($q) {
            if ($q === '') {
                return $rows;
            }
            $needle = mb_strtolower(ltrim($q, '@'));

            return $rows->filter(fn ($row) => str_contains(mb_strtolower($row['contact']->displayName().' '.$row['contact']->username.' '.$row['contact']->telegram_user_id), $needle));
        };
        $counts = collect($groups)->map(fn ($rows) => $search($rows)->count())->all();
        $rows = $search($groups[$tab])->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $people = new LengthAwarePaginator($rows->forPage($page, 50), $rows->count(), 50, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return view('admin.telegram.broadcasts.show', compact('broadcast', 'editable', 'tabs', 'tab', 'counts', 'people', 'q', 'stats') + [
            'topicUrl' => BroadcastDesk::topicUrl(),
        ]);
    }
}
