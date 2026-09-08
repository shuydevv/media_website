<?php

namespace App\Http\Controllers;

use App\Models\Impersonation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationLeaveController extends Controller
{
    /**
     * Возврат из-под ученика в аккаунт админа. Не под middleware 'admin' —
     * в момент вызова auth()->user() это ученик, AdminMiddleware дал бы 404.
     * impersonator_id читается только из серверной сессии (клиент не может
     * его подделать через куку — кука несёт лишь id сессии), поэтому
     * произвольный студент не может этим маршрутом залогиниться под кем-то.
     */
    public function __invoke(Request $request)
    {
        $adminId = session('impersonator_id');
        $studentId = $request->user()?->id;

        session()->forget(['impersonator_id', 'impersonator_name']);

        if (!$adminId) {
            abort(404);
        }

        Impersonation::where('admin_id', $adminId)
            ->where('user_id', $studentId)
            ->whereNull('ended_at')
            ->latest('id')
            ->first()
            ?->update(['ended_at' => now()]);

        $admin = User::find($adminId);

        if (!$admin || !$admin->isAdmin()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('status', 'Сеанс администратора недействителен — войдите заново.');
        }

        Auth::login($admin);

        return redirect()->route('admin.user.show', $studentId);
    }
}
