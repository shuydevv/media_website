<?php

namespace App\Service\Avito;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Тонкий клиент API Авито (мессенджер), без SDK — как TelegramApi.
 * Авторизация — client_credentials (client_id/client_secret из кабинета
 * «Авито для профессионалов» → API): токен живёт сутки, держим в кэше и
 * на 401 берём новый.
 */
class AvitoApi
{
    private const BASE = 'https://api.avito.ru';

    private const TOKEN_CACHE_KEY = 'avito.access_token';

    public function isConfigured(): bool
    {
        return filled(config('services.avito.client_id')) && filled(config('services.avito.client_secret'));
    }

    /** Чат: context.value — объявление (title, url, price_string), users — участники с именами. */
    public function chat(int $userId, string $chatId): array
    {
        return $this->request('get', "/messenger/v2/accounts/{$userId}/chats/{$chatId}");
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
            Cache::forget(self::TOKEN_CACHE_KEY);

            return $this->request($method, $path, $params, true);
        }
        if (! $response->successful()) {
            throw new AvitoApiException("{$path}: HTTP {$response->status()} ".mb_substr($response->body(), 0, 300), $response->status());
        }

        return $response->json() ?? [];
    }

    private function token(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        if (! $this->isConfigured()) {
            throw new AvitoApiException('AVITO_CLIENT_ID / AVITO_CLIENT_SECRET не заданы в .env');
        }

        try {
            $response = Http::timeout(15)->connectTimeout(5)->asForm()->post(self::BASE.'/token', [
                'grant_type' => 'client_credentials',
                'client_id' => config('services.avito.client_id'),
                'client_secret' => config('services.avito.client_secret'),
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
        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }
}
