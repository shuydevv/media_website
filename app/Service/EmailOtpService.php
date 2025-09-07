<?php
namespace App\Service;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use App\Notifications\VerifyEmailWithCode;

class EmailOtpService
{
    private int $ttlMinutes = 15;

    // Отправка кода + письма со ссылкой
    public function send(string $email, int $userId): string
    {
        $email = mb_strtolower(trim($email));
        $this->ensureCanSend($email);

        $vid  = (string) Str::ulid();
        $code = (string) random_int(100000, 999999);

        Cache::put($this->key($vid), [
            'email'        => $email,
            'code_hash'    => Hash::make($code),
            'attempts'     => 0,
            'resend_count' => 0,
            'expires_at'   => now()->addMinutes($this->ttlMinutes)->getTimestamp(),
        ], now()->addMinutes($this->ttlMinutes));

        RateLimiter::hit($this->sendKey($email), 60);

        // Уведомление: код + подписанная кнопка
        optional(auth()->user())->notify(new VerifyEmailWithCode($code, $userId));
        // Если пользователь не аутентифицирован: создателя письма нет — отправим через notifiable-объект
        if (!auth()->check()) {
            // Вручную "нотифицируем" (через notifiable route)
            (new class($email) {
                public function routeNotificationForMail() { return $this->email; }
                public function __construct(public string $email) {}
                public function notify($notification) { $notification->toMail($this); \Illuminate\Support\Facades\Notification::route('mail', $this->email)->notify($notification); }
            })->notify(new VerifyEmailWithCode($code, $userId));
        }

        return $vid;
    }

    public function resend(string $vid, int $userId): void
    {
        $data = Cache::get($this->key($vid));
        if (!$data) throw ValidationException::withMessages(['code' => 'Код истёк. Запросите новый.']);

        $email = $data['email'];
        $this->ensureCanSend($email);

        if (($data['resend_count'] ?? 0) >= 5) {
            throw ValidationException::withMessages(['code' => 'Превышен лимит повторных отправок.']);
        }

        $code = (string) random_int(100000, 999999);
        $data['code_hash']    = Hash::make($code);
        $data['resend_count'] = ($data['resend_count'] ?? 0) + 1;

        Cache::put($this->key($vid), $data, $data['expires_at'] - time());
        RateLimiter::hit($this->sendKey($email), 60);

        (new class($email) {
            public function routeNotificationForMail() { return $this->email; }
            public function __construct(public string $email) {}
            public function notify($notification) { $notification->toMail($this); \Illuminate\Support\Facades\Notification::route('mail', $this->email)->notify($notification); }
        })->notify(new VerifyEmailWithCode($code, $userId));
    }

    public function verify(string $vid, string $code): string
    {
        $data = Cache::get($this->key($vid));
        if (!$data) throw ValidationException::withMessages(['code' => 'Код истёк. Запросите новый.']);

        if (time() > ($data['expires_at'] ?? 0)) {
            Cache::forget($this->key($vid));
            throw ValidationException::withMessages(['code' => 'Код истёк. Запросите новый.']);
        }

        if (($data['attempts'] ?? 0) >= 5) {
            throw ValidationException::withMessages(['code' => 'Слишком много попыток. Запросите новый код.']);
        }

        $data['attempts'] = ($data['attempts'] ?? 0) + 1;
        Cache::put($this->key($vid), $data, $data['expires_at'] - time());

        if (!Hash::check($code, $data['code_hash'])) {
            throw ValidationException::withMessages(['code' => 'Неверный код.']);
        }

        // Успех
        Cache::forget($this->key($vid));
        return $data['email'];
    }

    public static function mask(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $mask = mb_substr($name, 0, 1) . str_repeat('•', max(0, mb_strlen($name) - 1));
        return $domain ? "{$mask}@{$domain}" : $mask;
    }

    private function ensureCanSend(string $email): void
    {
        $key = $this->sendKey($email);
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages(['email' => "Слишком частые запросы. Повторите через {$seconds} сек."]);
        }
    }

    private function key(string $vid): string { return "eotp:{$vid}"; }
    private function sendKey(string $email): string { return "eotp_send:{$email}"; }
}
