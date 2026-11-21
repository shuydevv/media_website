<?php

namespace App\Http\Controllers\Admin\Telegram\Avito;

use App\Http\Controllers\Controller;
use App\Models\AvitoAccount;
use Illuminate\Http\Request;

/**
 * Новый аккаунт: сохраняем и сразу проверяем ключи и подписываем webhook.
 * Не вышло — аккаунт остаётся с ошибкой в списке: ключи можно поправить.
 */
class StoreController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'client_id' => ['required', 'string', 'max:100', 'unique:avito_accounts,client_id'],
            'client_secret' => ['required', 'string', 'max:255'],
        ]);

        $account = AvitoAccount::create(array_map('trim', $data));

        return ConnectController::result($account, $account->connect());
    }
}
