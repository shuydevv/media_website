<?php

namespace App\Service;

use App\Notifications\VerifyEmailWithCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailOtpService
{
    private int $ttlMinutes = 15;

    // Отправка кода + письма со ссылкой
    public function send(string $email, int $userId): string
    {
        $email = mb_strtolower(trim($email));
        $this->ensureCanSend($email);

        $vid = (string) Str::ulid();
        $code = (string) random_int(100000, 999999);

        Cache::put($this->key($vid), [
            'email' => $email,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'resend_count' => 0,
            'expires_at' => now()->addMinutes($this->ttlMinutes)->getTimestamp(),
        ], now()->addMinutes($this->ttlMinutes));

        RateLimiter::hit($this->sendKey($email), 60);

        // Статус доставки для /auth/email/status (поллинг со страницы ввода кода) —
        // письмо уходит через очередь, тут мы ещё не знаем, дошло ли оно; статус
        // проставляют VerifyEmailWithCode::failed() и слушатель NotificationSent
        // в AppServiceProvider, когда джоба реально отработает.
        Cache::put($this->statusKey($vid), 'pending', now()->addMinutes($this->ttlMinutes));

        // Уведомление: код + подписанная кнопка
        optional(auth()->user())->notify(new VerifyEmailWithCode($code, $userId, $vid));
        // Если пользователь не аутентифицирован: создателя письма нет — отправим через notifiable-route
        if (! auth()->check()) {
            \Illuminate\Support\Facades\Notification::route('mail', $email)->notify(new VerifyEmailWithCode($code, $userId, $vid));
        }

        return $vid;
    }

    public function resend(string $vid, int $userId): void
    {
        $data = Cache::get($this->key($vid));
        if (! $data) {
            throw ValidationException::withMessages(['code' => 'Код истёк. Запросите новый.']);
        }

        $email = $data['email'];
        $this->ensureCanSend($email);

        if (($data['resend_count'] ?? 0) >= 5) {
            throw ValidationException::withMessages(['code' => 'Превышен лимит повторных отправок.']);
        }

        $code = (string) random_int(100000, 999999);
        $data['code_hash'] = Hash::make($code);
        $data['resend_count'] = ($data['resend_count'] ?? 0) + 1;
        // Новый код — новый бюджет попыток: иначе тот, кто ошибся 5 раз, запросил
        // код заново и ввёл его верно с первой попытки, всё равно получал бы
        // "Слишком много попыток" из-за attempts, унаследованного от старого кода.
        $data['attempts'] = 0;

        Cache::put($this->key($vid), $data, $data['expires_at'] - time());
        RateLimiter::hit($this->sendKey($email), 60);

        Cache::put($this->statusKey($vid), 'pending', now()->addMinutes($this->ttlMinutes));
        \Illuminate\Support\Facades\Notification::route('mail', $email)->notify(new VerifyEmailWithCode($code, $userId, $vid));
    }

    // Статус последней попытки доставки письма — 'pending'/'sent'/'failed', см. send().
    public function deliveryStatus(string $vid): ?string
    {
        return Cache::get($this->statusKey($vid));
    }

    public function verify(string $vid, string $code): string
    {
        $data = Cache::get($this->key($vid));
        if (! $data) {
            throw ValidationException::withMessages(['code' => 'Код истёк. Запросите новый.']);
        }

        if (time() > ($data['expires_at'] ?? 0)) {
            Cache::forget($this->key($vid));
            throw ValidationException::withMessages(['code' => 'Код истёк. Запросите новый.']);
        }

        if (($data['attempts'] ?? 0) >= 5) {
            throw ValidationException::withMessages(['code' => 'Слишком много попыток. Запросите новый код.']);
        }

        $data['attempts'] = ($data['attempts'] ?? 0) + 1;
        Cache::put($this->key($vid), $data, $data['expires_at'] - time());

        if (! Hash::check($code, $data['code_hash'])) {
            throw ValidationException::withMessages(['code' => 'Неверный код.']);
        }

        // Успех
        Cache::forget($this->key($vid));

        return $data['email'];
    }

    public static function mask(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $mask = mb_substr($name, 0, 1).str_repeat('•', max(0, mb_strlen($name) - 1));

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

    private function key(string $vid): string
    {
        return "eotp:{$vid}";
    }

    private function sendKey(string $email): string
    {
        return "eotp_send:{$email}";
    }

    public function statusKey(string $vid): string
    {
        return "eotp_status:{$vid}";
    }
}
