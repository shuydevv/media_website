<?php

namespace Tests\Unit\Service\Telegram;

use App\Service\Telegram\TelegramUsername;
use PHPUnit\Framework\TestCase;

class TelegramUsernameTest extends TestCase
{
    /** @test */
    public function every_common_way_of_writing_a_login_gives_the_same_value()
    {
        foreach (['ivan_petrov', '@ivan_petrov', ' @ivan_petrov ', 't.me/ivan_petrov', 'https://t.me/ivan_petrov', 'https://t.me/ivan_petrov/', 'telegram.me/ivan_petrov'] as $input) {
            $this->assertSame('ivan_petrov', TelegramUsername::normalize($input), $input);
        }
    }

    /** @test */
    public function phone_numbers_short_and_invalid_logins_are_rejected()
    {
        foreach (['', '@', '+79991234567', '89991234567', 'ivan', '1ivanov', 'иван_петров', 'ivan petrov', str_repeat('a', 33)] as $input) {
            $this->assertNull(TelegramUsername::normalize($input), $input);
        }
    }
}
