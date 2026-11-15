<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ExcludedController extends Controller
{
    /**
     * Переключатель «Не учитывать в CRM» (User::$crm_excluded). Доступ к
     * курсам, оплаты и даты не меняются — ученик только перестаёт попадать
     * в статусы/счётчики/доход и переезжает на вкладку «Не учитываются».
     */
    public function __invoke(Request $request, User $user)
    {
        $data = $request->validate(['excluded' => ['required', 'boolean']]);

        $user->crm_excluded = (bool) $data['excluded'];
        $user->save();

        $name = trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: ($user->email ?: 'Ученик');

        return back()->with('status', $user->crm_excluded
            ? "{$name}: больше не учитывается в CRM, доступ сохранён"
            : "{$name}: снова учитывается в CRM");
    }
}
