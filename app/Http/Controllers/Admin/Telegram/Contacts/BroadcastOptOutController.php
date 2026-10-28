<?php

namespace App\Http\Controllers\Admin\Telegram\Contacts;

use App\Http\Controllers\Controller;
use App\Models\TelegramContact;
use App\Service\Telegram\Broadcast\BroadcastDesk;
use Illuminate\Http\Request;

/**
 * «Не присылать рассылки» в карточке человека — то же, что /norassylka в
 * его теме: исключает человека (все его контакты), а не один бот.
 */
class BroadcastOptOutController extends Controller
{
    public function __invoke(Request $request, TelegramContact $contact)
    {
        $optOut = $request->boolean('opt_out');
        BroadcastDesk::make()->setOptOut([(int) $contact->telegram_user_id], $optOut);

        return back()->with('success', $optOut ? 'Больше не получает рассылки.' : 'Снова получает рассылки.');
    }
}
