<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Вход по постоянной личной ссылке /enter/{token} (выпускается учеником в
 * Профиль → Аккаунт, см. Student\ProfileController::issueLoginLink).
 * Ссылка многоразовая — живёт, пока ученик её не перевыпустит или не
 * отключит.
 *
 * Не под middleware guest: если в браузере уже открыт другой аккаунт,
 * переключаемся на владельца ссылки — иначе ссылка «молча не работала» бы.
 *
 * Ботов-превьюшников (Telegram и мессенджеры сами открывают ссылку, чтобы
 * нарисовать превью) отсекаем по User-Agent, как в Telegram\LessonLinkController,
 * — иначе в «последний вход по ссылке» писался бы бот.
 */
class LoginLinkController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        if (preg_match('/bot|crawler|spider|preview|facebookexternalhit/i', (string) $request->userAgent())) {
            return redirect()->route('login');
        }

        $user = User::findByLoginToken($token);
        if (! $user || ! $user->canUseLoginLink()) {
            return redirect()->route('login')
                ->with('status', 'Ссылка для входа недействительна: возможно, её перевыпустили. Войдите обычным способом.');
        }

        if (Auth::id() !== $user->id) {
            if (Auth::check()) {
                Auth::logout();
                $request->session()->invalidate();
            }

            Auth::login($user, true);
            $request->session()->regenerate();
        }

        $user->forceFill([
            'login_token_used_at' => now(),
            'login_token_used_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ])->save();

        // Токен не должен уехать дальше в Referer.
        return redirect('/home')->header('Referrer-Policy', 'no-referrer');
    }
}
