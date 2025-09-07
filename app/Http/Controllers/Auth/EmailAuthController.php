<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Service\EmailOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class EmailAuthController extends Controller
{
    public function __construct(private EmailOtpService $otp) {}

    public function showEmailForm() {
        return view('auth.email');
    }

    public function send(Request $request) {
        $data = $request->validate([
            'email' => ['required','email','max:255'],
        ]);
        $email = mb_strtolower(trim($data['email']));

        $user = User::where('email', $email)->first();
        $isNew = false;

        if (!$user) {
            $user = User::create([
                'email'    => $email,
                'name'     => 'Новый пользователь',
                'locale'   => app()->getLocale(),
                'timezone' => config('app.timezone'),
                'password' => Hash::make(Str::random(32)), // технический пароль до онбординга
                'role'     => 2,
            ]);
            $isNew = true;
        } elseif ($user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['email' => 'Эта почта уже подтверждена. Войдите в аккаунт.']);
        }

        $vid = $this->otp->send($email, $user->id);

        // Сохраняем контекст в сессии для формы ввода кода
        session()->put('eotp_vid', $vid);
        session()->put('eotp_masked_email', EmailOtpService::mask($email));
        session()->put('eotp_user_id', $user->id);

        return redirect()->route('auth.email.verify.show')
            ->with('status', $isNew ? 'Мы создали аккаунт и отправили код на почту.' : 'Код отправлен на почту.');
    }

    public function showVerifyForm() {
        abort_unless(session()->has('eotp_vid'), 419);
        return view('auth.email-verify', [
            'masked' => session('eotp_masked_email'),
        ]);
    }

    public function verify(Request $request) {
        abort_unless(session()->has('eotp_vid'), 419);
        $data = $request->validate([
            'code' => ['required','digits:6'],
        ]);

        $vid = session('eotp_vid');
        $email = $this->otp->verify($vid, $data['code']);

        $userId = (int) session('eotp_user_id');
        $user   = User::findOrFail($userId);

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        Auth::login($user);

        // очистим sso-данные
        session()->forget(['eotp_vid','eotp_masked_email','eotp_user_id']);

        return redirect()->route('onboarding.profile.show');
    }

    public function resend() {
        abort_unless(session()->has('eotp_vid') && session()->has('eotp_user_id'), 419);
        $this->otp->resend(session('eotp_vid'), (int) session('eotp_user_id'));
        return back()->with('status', 'Код отправлен повторно.');
    }

    public function verifyByLink(Request $request, int $id) {
        $user = User::findOrFail($id);

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        Auth::login($user);

        // после клика по письму — сразу на онбординг
        return redirect()->route('onboarding.profile.show');
    }
}
