<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    use SendsPasswordResetEmails;

    public function __construct()
    {
        // ограничим частоту отправки: не чаще 5 раз в минуту
        $this->middleware('throttle:5,1')->only('sendResetLinkEmail');
    }

    public function sendResetLinkEmail(Request $request)
    {
        $this->validateEmail($request);
        $credentials = ['email' => trim((string) $request->input('email'))];

        // Письмо уходит синхронно (MailChannel шлёт Mailable напрямую, ShouldQueue
        // у NotificationMail тут не срабатывает), а токен брокер создаёт ДО отправки.
        // Если SMTP упал — без этого catch пользователь видел 500, а повторное
        // нажатие упиралось в throttle ("подождите"), хотя письмо так и не ушло.
        try {
            $response = $this->broker()->sendResetLink($credentials);
        } catch (\Throwable $e) {
            if ($user = $this->broker()->getUser($credentials)) {
                $this->broker()->deleteToken($user);
            }
            Log::error('Password reset mail failed', ['email' => $credentials['email'], 'error' => $e->getMessage()]);
            report($e);

            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Не удалось отправить письмо. Попробуйте ещё раз через минуту или напишите в поддержку.',
            ]);
        }

        if ($response === Password::RESET_LINK_SENT) {
            Log::info('Password reset link sent', ['email' => $credentials['email']]);

            return back()
                ->withInput($request->only('email'))
                ->with('status', 'Ссылка для сброса пароля отправлена на ' . $credentials['email'] . '. Письмо обычно приходит в течение пары минут — проверьте также папку «Спам».')
                ->with('resend_in', $this->throttleSeconds());
        }

        if ($response === Password::RESET_THROTTLED) {
            $wait = $this->secondsUntilResend($credentials);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => "Ссылка уже отправлена. Повторно запросить можно через {$wait} сек. Проверьте папку «Спам»."])
                ->with('resend_in', $wait);
        }

        return $this->sendResetLinkFailedResponse($request, $response);
    }

    private function throttleSeconds(): int
    {
        return (int) config('auth.passwords.' . config('auth.defaults.passwords') . '.throttle', 60);
    }

    private function secondsUntilResend(array $credentials): int
    {
        $user = $this->broker()->getUser($credentials);
        $table = config('auth.passwords.' . config('auth.defaults.passwords') . '.table');
        $createdAt = $user
            ? DB::table($table)->where('email', $user->getEmailForPasswordReset())->value('created_at')
            : null;

        if (! $createdAt) {
            return $this->throttleSeconds();
        }

        $left = Carbon::parse($createdAt)->addSeconds($this->throttleSeconds())->getTimestamp() - now()->getTimestamp();

        return max(1, $left);
    }
}
