<?php

namespace App\Models;

use App\Service\Avito\AvitoApi;
use App\Service\Avito\AvitoApiException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Аккаунт Авито со своими ключами API (админка «Telegram-бот → Авито»).
 * Сообщение webhook-а знает только id профиля (user_id) — по нему и ищем
 * аккаунт, чтобы взять его ключи для чтения чата и ответа.
 */
class AvitoAccount extends Model
{
    protected $fillable = ['name', 'client_id', 'client_secret', 'avito_user_id', 'profile_name', 'subscribed_at', 'last_error'];

    protected $hidden = ['client_secret'];

    protected $casts = [
        'client_secret' => 'encrypted',
        'avito_user_id' => 'integer',
        'subscribed_at' => 'datetime',
    ];

    public function api(): AvitoApi
    {
        return new AvitoApi($this);
    }

    /**
     * Аккаунт профиля $userId. Аккаунт без avito_user_id (перенесён из .env
     * или ещё не проверен) узнаёт свой id тут же — один запрос, дальше из БД.
     */
    public static function forUser(int $userId): ?self
    {
        if ($account = self::where('avito_user_id', $userId)->first()) {
            return $account;
        }

        foreach (self::whereNull('avito_user_id')->get() as $account) {
            try {
                $account->refreshProfile();
            } catch (AvitoApiException $e) {
                Log::warning('Avito: не удалось узнать профиль аккаунта', ['account' => $account->id, 'error' => $e->getMessage()]);

                continue;
            }
            if ($account->avito_user_id === $userId) {
                return $account;
            }
        }

        return null;
    }

    /** @throws AvitoApiException */
    public function refreshProfile(): void
    {
        $self = $this->api()->self();
        if (empty($self['id'])) {
            throw new AvitoApiException('Авито не вернул id профиля');
        }

        $taken = self::where('avito_user_id', (int) $self['id'])->whereKeyNot($this->id)->first();
        if ($taken) {
            throw new AvitoApiException("Этот профиль Авито уже подключён как «{$taken->name}»");
        }

        $this->forceFill(['avito_user_id' => (int) $self['id'], 'profile_name' => $self['name'] ?? null])->save();
    }

    /**
     * Проверить ключи и подписать webhook (у каждого аккаунта подписка своя,
     * адрес общий — аккаунт различаем по user_id в сообщении). Итог — в
     * subscribed_at / last_error, для админки.
     */
    public function connect(): bool
    {
        try {
            $this->refreshProfile();
            $this->api()->subscribe(self::webhookUrl());
        } catch (AvitoApiException $e) {
            $this->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 1000)])->save();

            return false;
        }

        $this->forceFill(['subscribed_at' => now(), 'last_error' => null])->save();

        return true;
    }

    /** Снять подписку (перед удалением); сбой — не повод не удалять. */
    public function disconnect(): void
    {
        try {
            $this->api()->unsubscribe(self::webhookUrl());
        } catch (AvitoApiException $e) {
            Log::warning('Avito: не удалось отписать аккаунт', ['account' => $this->id, 'error' => $e->getMessage()]);
        }
    }

    /** @throws AvitoApiException */
    public static function webhookUrl(): string
    {
        $secret = (string) config('services.avito.webhook_secret');
        if ($secret === '') {
            throw new AvitoApiException('AVITO_WEBHOOK_SECRET не задан в .env');
        }

        return route('avito.webhook', ['secret' => $secret]);
    }
}
