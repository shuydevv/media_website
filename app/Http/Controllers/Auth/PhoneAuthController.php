<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PhoneRequest;
use App\Http\Requests\Auth\OtpVerifyRequest;
use App\Service\OtpService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class PhoneAuthController extends Controller
{
    public function __construct(private OtpService $otp) {}

    public function showPhoneForm() {
        return view('auth.phone');
    }

    public function sendCode(PhoneRequest $request) {
        $phone = \App\Service\OtpService::normalizePhone($request->input('phone'));
        $vid = $this->otp->createChallenge($phone);
        session(['otp_vid' => $vid]);

        // маскировка для UI
        $masked = preg_replace('/^(\+\d{1,3})\d+(\d{2})$/', '$1*** *** ** $2', $phone);
        return redirect()->route('auth.phone.verify.show')->with('masked_phone', $masked);
    }

    public function showVerifyForm() {
        abort_unless(session()->has('otp_vid'), 419);
        return view('auth.phone-verify', ['masked' => session('masked_phone')]);
    }

    public function verifyCode(OtpVerifyRequest $request) {
        $vid = session('otp_vid');
        abort_unless($vid, 419);

        $phone = $this->otp->verify($vid, $request->input('code'));

        $user = User::query()->where('phone', $phone)->first();
        $isNew = false;

        if (!$user) {

            // Сгенерим уникальный плейсхолдер e-mail, чтобы пройти NOT NULL + UNIQUE
            $placeholderEmail = sprintf('%s@phone.local', preg_replace('/\D/', '', $phone));

            $user = User::create([
                'phone' => $phone,
                'email' => $placeholderEmail,
                'name'  => 'Новый пользователь',
                'locale'=> app()->getLocale(),
                'timezone' => config('app.timezone'),
                'password' => bcrypt(str()->random(32)), // технический
            ]);
            $isNew = true;
        }

        $user->forceFill(['phone_verified_at' => now()])->saveQuietly();
        session()->forget('otp_vid');

        Auth::login($user, true);

        // Если новый или нет имени — на анкету
        if ($isNew || empty($user->first_name)) {
            return redirect()->route('onboarding.profile.show');
        }
        return redirect()->intended(route('student.dashboard'));
    }

    public function resend() {
        $vid = session('otp_vid');
        abort_unless($vid, 419);
        $this->otp->resend($vid);
        return back()->with('status', 'Код отправлен повторно.');
    }
}
