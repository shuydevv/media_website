<?php

namespace Tests\Unit\Service\Telegram;

use App\Service\Telegram\WebAppInitData;
use PHPUnit\Framework\TestCase;

class WebAppInitDataTest extends TestCase
{
    private const TOKEN = '123456:test-token';

    /** initData так, как его подписывает Telegram. */
    private function sign(array $fields, string $token = self::TOKEN): string
    {
        ksort($fields);
        $check = implode("\n", array_map(fn ($k, $v) => "{$k}={$v}", array_keys($fields), $fields));
        $fields['hash'] = hash_hmac('sha256', $check, hash_hmac('sha256', $token, 'WebAppData', true));

        return http_build_query($fields);
    }

    private function fields(int $authDate): array
    {
        return [
            'query_id' => 'AAH',
            'user' => json_encode(['id' => 555, 'first_name' => 'Аня', 'username' => 'anya'], JSON_UNESCAPED_UNICODE),
            'auth_date' => (string) $authDate,
        ];
    }

    public function test_valid_data_gives_user(): void
    {
        $user = WebAppInitData::user($this->sign($this->fields(1000)), self::TOKEN, 3600, 1500);

        $this->assertSame(555, $user['id']);
        $this->assertSame('Аня', $user['first_name']);
    }

    public function test_other_bot_token_is_rejected(): void
    {
        $this->assertNull(WebAppInitData::user($this->sign($this->fields(1000), '999:other'), self::TOKEN, 3600, 1500));
    }

    public function test_tampered_user_is_rejected(): void
    {
        $data = str_replace('555', '556', $this->sign($this->fields(1000)));

        $this->assertNull(WebAppInitData::user($data, self::TOKEN, 3600, 1500));
    }

    public function test_old_data_is_rejected(): void
    {
        $this->assertNull(WebAppInitData::user($this->sign($this->fields(1000)), self::TOKEN, 3600, 1000 + 3601));
    }

    public function test_empty_token_or_data(): void
    {
        $this->assertNull(WebAppInitData::user('', self::TOKEN, 3600));
        $this->assertNull(WebAppInitData::user($this->sign($this->fields(time())), '', 3600));
    }
}
