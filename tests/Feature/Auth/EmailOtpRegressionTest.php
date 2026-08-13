<?php

namespace Tests\Feature\Auth;

use App\Notifications\VerifyEmailWithCode;
use App\Service\EmailOtpService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Регресс на баг из аудита 2026-08-13: EmailOtpService::resend() выдавал новый код,
 * но не сбрасывал attempts. Пользователь, ошибившийся 5 раз, нажимал "код не пришёл —
 * отправить снова", получал новый код, вводил его верно — и всё равно упирался в
 * "Слишком много попыток", потому что счётчик пережил resend(). Единственный выход
 * был начинать весь флоу заново. Как и в PromoCodeRedemptionTest — без
 * RefreshDatabase/DatabaseTransactions (users на MyISAM), но тут БД вообще не нужна —
 * состояние OTP целиком в кэше.
 */
class EmailOtpRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function latestCodeFor(string $vid): string
    {
        $notification = Notification::sent(
            new AnonymousNotifiable,
            VerifyEmailWithCode::class,
            fn (VerifyEmailWithCode $n) => $n->vid === $vid
        )->last();

        $this->assertNotNull($notification, "Не нашли отправленный VerifyEmailWithCode для vid={$vid}");

        return $notification->code;
    }

    /** @test */
    public function resend_resets_the_failed_attempts_counter(): void
    {
        $otp = app(EmailOtpService::class);
        $vid = $otp->send('regression-otp@example.test', 1);

        // Промахнулись 5 раз подряд первым кодом — исчерпали лимит попыток.
        for ($i = 0; $i < 5; $i++) {
            try {
                $otp->verify($vid, '000000');
            } catch (ValidationException) {
                // ожидаемо — код неверный, копим попытки
            }
        }

        // send() уже "потратил" 60-секундное окно троттлинга на этот email — не то,
        // что тут проверяется, просто перематываем время, чтобы resend() не упёрся в
        // "Слишком частые запросы" раньше, чем мы доберёмся до проверяемого бага.
        $this->travel(61)->seconds();

        $otp->resend($vid, 1);
        $freshCode = $this->latestCodeFor($vid);

        // Раньше падало именно тут: attempts из старого кода переживал resend(),
        // и даже верный свежий код отклонялся с "Слишком много попыток".
        $email = $otp->verify($vid, $freshCode);
        $this->assertSame('regression-otp@example.test', $email);
    }

    /** @test */
    public function verifying_with_the_original_code_still_works(): void
    {
        $otp = app(EmailOtpService::class);
        $vid = $otp->send('regression-otp-2@example.test', 1);

        $email = $otp->verify($vid, $this->latestCodeFor($vid));

        $this->assertSame('regression-otp-2@example.test', $email);
    }
}
