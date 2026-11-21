<?php

namespace App\Http\Controllers\Admin\Telegram\Avito;

use App\Http\Controllers\Controller;
use App\Models\AvitoAccount;

/**
 * Отписать webhook и удалить. Старые уведомления в теме остаются, но
 * ответить на них реплаем уже нельзя — ключей нет.
 */
class DestroyController extends Controller
{
    public function __invoke(AvitoAccount $account)
    {
        $account->disconnect();
        $account->delete();

        return redirect()->route('admin.telegram.avito.index')->with('success', "«{$account->name}» отключён.");
    }
}
