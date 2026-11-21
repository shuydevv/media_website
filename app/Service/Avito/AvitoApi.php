<?php

namespace App\Service\Avito;

use App\Models\AvitoAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Тонкий клиент API Авито (мессенджер), без SDK — как TelegramApi. Один
 * клиент — один аккаунт (AvitoAccount, ключи из админки «Telegram-бот →
 * Авито»). Авторизация — client_credentials: токен живёт сутки, держим в
 * кэше (ключ — по аккаунту и его ключам: сменили ключи — новый токен) и на
 * 401 берём новый.
 */
class AvitoApi
{
    private const BASE = 'https://api.avito.ru';

    public function __construct(private readonly AvitoAccount $account)
    {
    }

    /**
     * Клиент аккаунта, на который пришло сообщение ($userId — user_id из
     * webhook-а).
     *
     * @throws AvitoApiException
     */
    public static function forUser(int $userId): self
    {
        $account = AvitoAccount::forUser($userId);
        if (! $account) {
            throw new AvitoApiException("Аккаунт Авито {$userId} не подключён (админка → Telegram-бот → Авито)");
        }

        return $account->api();
    }

    private function tokenCacheKey(): string
    {
        return 'avito.access_token.'.$this->account->id.'.'.md5($this->account->client_id.'|'.$this->account->client_secret);
    }

    /** Чат: context.value — объявление (title, url, price_string), users — участники с именами. */
    public function chat(int $userId, string $chatId): array
    {
        return $this->request('get', "/messenger/v2/accounts/{$userId}/chats/{$chatId}");
    }

    /** Текстовое сообщение в чат от имени аккаунта $userId. */
    public function sendMessage(int $userId, string $chatId, string $text): array
    {
        return $this->request('post', "/messenger/v1/accounts/{$userId}/chats/{$chatId}/messages", [
            'message' => ['text' => $text],
            'type' => 'text',
        ]);
    }

    public function markRead(int $userId, string $chatId): array
    {
        return $this->request('post', "/messenger/v1/accounts/{$userId}/chats/{$chatId}/read");
    }

    public function self(): array
    {
        return $this->request('get', '/core/v1/accounts/self');
    }

    public function subscribe(string $url): array
    {
        return $this->request('post', '/messenger/v3/webhook', ['url' => $url]);
    }

    public function unsubscribe(string $url): array
    {
        return $this->request('post', '/messenger/v1/webhook/unsubscribe', ['url' => $url]);
    }

    /** @return array<int, array{url: string, version: string}> */
    public function subscriptions(): array
    {
        return $this->request('post', '/messenger/v1/subscriptions')['subscriptions'] ?? [];
    }

    /**
     * @throws AvitoApiException
     */
    private function request(string $method, string $path, array $params = [], bool $retried = false): array
    {
        try {
            $response = Http::timeout(15)->connectTimeout(5)
                ->withToken($this->token())
                ->acceptJson()
                ->{$method}(self::BASE.$path, $params === [] && $method === 'post' ? (object) [] : $params);
        } catch (AvitoApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new AvitoApiException("{$path}: ".$e->getMessage());
        }

        if ($response->status() === 401 && ! $retried) {
            Cache::forget($this->tokenCacheKey());

            return $this->request($method, $path, $params, true);
        }
        if (! $response->successful()) {
            throw new AvitoApiException("{$path}: HTTP {$response->status()} ".mb_substr($response->body(), 0, 300), $response->status());
        }

        return $response->json() ?? [];
    }

    private function token(): string
    {
        $cached = Cache::get($this->tokenCacheKey());
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::timeout(15)->connectTimeout(5)->asForm()->post(self::BASE.'/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $this->account->client_id,
                'client_secret' => $this->account->client_secret,
            ]);
        } catch (\Throwable $e) {
            throw new AvitoApiException('token: '.$e->getMessage());
        }

        $token = $response->json('access_token');
        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new AvitoApiException('token: HTTP '.$response->status().' '.mb_substr($response->body(), 0, 300), $response->status());
        }

        // Запас в 5 минут, чтобы не отправить запрос с токеном на излёте.
        $ttl = max(60, (int) ($response->json('expires_in') ?? 86400) - 300);
        Cache::put($this->tokenCacheKey(), $token, $ttl);

        return $token;
    }
}
