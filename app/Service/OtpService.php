<?php
namespace App\Service;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Service\Sms\SmsSender;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function __construct(private SmsSender $sms) {}

    // Нормализация в E.164 (простая версия)
    public static function normalizePhone(string $raw): string {
        $p = preg_replace('/[^\d\+]/', '', $raw);
        if (str_starts_with($p, '+')) return $p;
        // Пример «по-умолчанию»: RU (+7) — при необходимости адаптируй
        if (preg_match('/^\d{10}$/', $p)) return '+7'.$p;
        throw ValidationException::withMessages(['phone' => 'Укажите номер в международном формате, например +7XXXXXXXXXX']);
    }

    public function createChallenge(string $phone): string {
        $this->ensureCanSend($phone);

        $vid  = (string) Str::ulid();
        $code = config('app.debug') && env('OTP_FIXED_CODE') ? env('OTP_FIXED_CODE') : (string) random_int(100000, 999999);
        $hash = Hash::make($code);

        $ttl  = now()->addMinutes(10);
        Cache::put($this->key($vid), [
            'phone'        => $phone,
            'code_hash'    => $hash,
            'attempts'     => 0,
            'resend_count' => 0,
            'expires_at'   => $ttl->timestamp,
        ], $ttl);

        $this->sms->send($phone, "Код входа: {$code}. Действителен 10 минут.");
        RateLimiter::hit($this->sendKey($phone), 60); // окно троттлинга 60 сек

        return $vid;
    }

    public function resend(string $vid): void {
        $data = Cache::get($this->key($vid));
        if (!$data) throw ValidationException::withMessages(['code' => 'Код истёк. Запросите новый.']);
        $phone = $data['phone'];

        $this->ensureCanSend($phone);
        if (($data['resend_count'] ?? 0) >= 5) {
            throw ValidationException::withMessages(['phone' => 'Превышен лимит повторных отправок. Попробуйте позже.']);
        }

        $code = config('app.debug') && env('OTP_FIXED_CODE') ? env('OTP_FIXED_CODE') : (string) random_int(100000, 999999);
        $data['code_hash']    = Hash::make($code);
        $data['resend_count'] = ($data['resend_count'] ?? 0) + 1;

        Cache::put($this->key($vid), $data, $data['expires_at'] - time());
        $this->sms->send($phone, "Код входа: {$code}. Действителен 10 минут.");
        RateLimiter::hit($this->sendKey($phone), 60);
    }

    public function verify(string $vid, string $code): string {
        $data = Cache::get($this->key($vid));
        if (!$data) throw ValidationException::withMessages(['code' => 'Код истёк или неверный. Запросите новый.']);

        if (($data['attempts'] ?? 0) >= 5) {
            throw ValidationException::withMessages(['code' => 'Превышено число попыток. Запросите новый код.']);
        }
        if (!Hash::check($code, $data['code_hash'])) {
            $data['attempts'] = ($data['attempts'] ?? 0) + 1;
            Cache::put($this->key($vid), $data, $data['expires_at'] - time());
            throw ValidationException::withMessages(['code' => 'Код не подходит.']);
        }

        // успех
        Cache::forget($this->key($vid));
        return $data['phone'];
    }

    private function ensureCanSend(string $phone): void {
        $key = $this->sendKey($phone);
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages(['phone' => "Запросы слишком частые. Повторите через {$seconds} сек."]);
        }
    }

    private function key(string $vid): string { return "otp:{$vid}"; }
    private function sendKey(string $phone): string { return "otp_send:{$phone}"; }
}
