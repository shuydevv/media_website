<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Service\EmailOtpService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailAuthController extends Controller
{
    public function __construct(private EmailOtpService $otp)
    {
    }

    public function showEmailForm()
    {
        return view('auth.email');
    }

    public function send(Request $request)
    {
        // Honeypot: поле скрыто от людей вёрсткой, обычный бот его заполняет.
        // Отвечаем тем же самым "успехом", что и на реальный запрос, чтобы бот
        // не понял, что его отсеяли, но код не отправляем и юзера не создаём.
        if ($request->filled('hp_website')) {
            return redirect()->route('auth.email.show')
                ->with('status', 'Если такой адрес существует, мы отправили код.');
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);
        $email = mb_strtolower(trim($data['email']));

        $user = User::where('email', $email)->first();
        $isNew = false;

        if (! $user) {
            try {
                $user = User::create([
                    'email' => $email,
                    'name' => 'Новый пользователь',
                    'locale' => app()->getLocale(),
                    'timezone' => config('app.timezone'),
                    'password' => Hash::make(Str::random(32)), // технический пароль до онбординга
                    'role' => 2,
                ]);
                $isNew = true;
            } catch (QueryException $e) {
                // Двойной тап на форме (нет client-side блокировки повторного сабмита
                // на медленной мобильной сети) — два параллельных запроса оба прошли
                // проверку "такого email ещё нет" до того, как первый успел вставить
                // строку. Ловим только нарушение уникального индекса email (MySQL 1062)
                // и продолжаем как с уже существующим пользователем — любая другая
                // ошибка БД должна падать как обычно, а не маскироваться.
                if (($e->errorInfo[1] ?? null) !== 1062) {
                    throw $e;
                }
                $user = User::where('email', $email)->firstOrFail();
                $this->assertNotVerified($user);
            }
        } else {
            $this->assertNotVerified($user);
        }

        $vid = $this->otp->send($email, $user->id);

        // Сохраняем контекст в сессии для формы ввода кода
        session()->put('eotp_vid', $vid);
        session()->put('eotp_masked_email', EmailOtpService::mask($email));
        session()->put('eotp_user_id', $user->id);

        return redirect()->route('auth.email.verify.show')
            ->with('status', $isNew ? 'Мы создали аккаунт и отправили код на почту.' : 'Код отправлен на почту.');
    }

    public function showVerifyForm()
    {
        abort_unless(session()->has('eotp_vid'), 419);

        return view('auth.email-verify', [
            'masked' => session('eotp_masked_email'),
        ]);
    }

    public function verify(Request $request)
    {
        abort_unless(session()->has('eotp_vid'), 419);
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $vid = session('eotp_vid');
        $email = $this->otp->verify($vid, $data['code']);

        $userId = (int) session('eotp_user_id');
        $user = User::findOrFail($userId);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        Auth::login($user);

        // очистим sso-данные
        session()->forget(['eotp_vid', 'eotp_masked_email', 'eotp_user_id']);

        return redirect()->route('onboarding.profile.show');
    }

    public function resend()
    {
        abort_unless(session()->has('eotp_vid') && session()->has('eotp_user_id'), 419);
        $this->otp->resend(session('eotp_vid'), (int) session('eotp_user_id'));

        return back()->with('status', 'Код отправлен повторно.');
    }

    // Опрашивается со страницы ввода кода (JS), пока письмо ещё в очереди —
    // см. VerifyEmailWithCode::failed() и слушатель NotificationSent в AppServiceProvider.
    public function status()
    {
        abort_unless(session()->has('eotp_vid'), 419);

        return response()->json([
            'status' => $this->otp->deliveryStatus(session('eotp_vid')) ?? 'pending',
        ]);
    }

    public function verifyByLink(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        Auth::login($user);

        // после клика по письму — сразу на онбординг
        return redirect()->route('onboarding.profile.show');
    }

    /**
     * Вход по ссылке-приглашению из письма админ-созданного аккаунта
     * (Admin\User\StoreController) — не путать с verifyByLink() выше
     * (та — для самостоятельной OTP-регистрации).
     *
     * Переход по ссылке сам по себе считается подтверждением почты (раз
     * письмо дошло и ссылка открыта — доступ к почте доказан), но "ссылка
     * использована" проверяем по profile_completed_at, а не по
     * hasVerifiedEmail(): email помечается верифицированным сразу при первом
     * клике, а профиль/пароль ученик заполняет уже ПОСЛЕ, на онбординге
     * (гейт — EnsureProfileCompleted). Если проверять по verified, повторный
     * переход по той же ссылке до завершения регистрации (например, открыл
     * с телефона, не закончил, вернулся с компьютера) ошибочно считался бы
     * "уже использованной".
     */
    public function inviteLogin(int $id)
    {
        $user = User::findOrFail($id);

        if ($user->profile_completed_at) {
            return redirect()->route('login')
                ->with('status', 'Эта ссылка уже использована. Войдите обычным способом.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        Auth::login($user, true);

        return redirect()->route('onboarding.profile.show');
    }

    private function assertNotVerified(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['email' => 'Эта почта уже подтверждена. Войдите в аккаунт.']);
        }
    }
}
