<?php

namespace App\Http\Controllers\Admin\Telegram\Avito;

use App\Http\Controllers\Controller;
use App\Models\AvitoAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Название и ключи. Пустой секрет — оставить прежний. Сменили ключи — проверяем заново. */
class UpdateController extends Controller
{
    public function __invoke(Request $request, AvitoAccount $account)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'client_id' => ['required', 'string', 'max:100', Rule::unique('avito_accounts', 'client_id')->ignore($account->id)],
            'client_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $account->name = trim($data['name']);
        $account->client_id = trim($data['client_id']);
        if (filled($data['client_secret'] ?? null)) {
            $account->client_secret = trim($data['client_secret']);
        }
        $keysChanged = $account->isDirty(['client_id', 'client_secret']);
        $account->save();

        if (! $keysChanged) {
            return back()->with('success', 'Сохранено.');
        }

        // Новые ключи могут быть от другого профиля — id узнаем заново.
        $account->forceFill(['avito_user_id' => null, 'profile_name' => null, 'subscribed_at' => null])->save();

        return ConnectController::result($account, $account->connect());
    }
}
