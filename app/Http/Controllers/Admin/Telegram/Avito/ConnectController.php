<?php

namespace App\Http\Controllers\Admin\Telegram\Avito;

use App\Http\Controllers\Controller;
use App\Models\AvitoAccount;

/** «Проверить и подписать»: ключи, профиль, подписка на webhook. */
class ConnectController extends Controller
{
    public function __invoke(AvitoAccount $account)
    {
        return self::result($account, $account->connect());
    }

    public static function result(AvitoAccount $account, bool $ok)
    {
        $back = redirect()->route('admin.telegram.avito.index');

        return $ok
            ? $back->with('success', "«{$account->name}» подключён: сообщения будут приходить в тему «🛒 Авито».")
            : $back->with('error', "«{$account->name}»: не удалось подключить — {$account->last_error}");
    }
}
