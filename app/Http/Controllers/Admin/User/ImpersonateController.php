<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Models\Impersonation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonateController extends Controller
{
    /**
     * Вход админа под учеником. Только ROLE_READER — куратора или другого
     * админа так не открыть, это не "просмотр", а вход в чужой
     * административный/куратор-доступ. Auth::login() внутри сам делает
     * session()->migrate(true) (ротация id + уничтожение старой строки
     * сессии) — ручной regenerate() здесь не нужен, см. тот же паттерн без
     * regenerate() в Auth\EmailAuthController/PhoneAuthController.
     */
    public function __invoke(Request $request, User $user)
    {
        if (session()->has('impersonator_id')) {
            return back()->with('error', 'Сначала завершите текущий сеанс просмотра — нельзя войти под другим учеником поверх текущего.');
        }

        if (!$user->isStudent()) {
            return back()->with('error', 'Входить можно только под учеником.');
        }

        $admin = $request->user();

        Impersonation::create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        session([
            'impersonator_id' => $admin->id,
            'impersonator_name' => $admin->name,
        ]);

        Auth::login($user);

        return redirect()->route('student.dashboard');
    }
}
