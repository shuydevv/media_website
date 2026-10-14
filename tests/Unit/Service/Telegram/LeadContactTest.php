<?php

namespace Tests\Unit\Service\Telegram;

use App\Service\Telegram\LeadContact;
use PHPUnit\Framework\TestCase;

class LeadContactTest extends TestCase
{
    /** @test */
    public function russian_numbers_in_any_format_become_plus_seven()
    {
        foreach (['+7 999 123-45-67', '89991234567', '8 (999) 123 45 67', '79991234567', '9991234567', '+7(999)1234567'] as $input) {
            $this->assertSame(['telegram' => null, 'phone' => '+79991234567'], LeadContact::parse($input), $input);
        }
    }

    /** @test */
    public function foreign_numbers_need_a_plus()
    {
        $this->assertSame(['telegram' => null, 'phone' => '+375291234567'], LeadContact::parse('+375 29 123 45 67'));
        $this->assertNull(LeadContact::parse('375291234567'));
        $this->assertNull(LeadContact::parse('12345'));
    }

    /** @test */
    public function logins_go_through_username_normalisation()
    {
        $this->assertSame(['telegram' => 'ivan_petrov', 'phone' => null], LeadContact::parse('@ivan_petrov'));
        $this->assertSame(['telegram' => 'ivan_petrov', 'phone' => null], LeadContact::parse('t.me/ivan_petrov'));
        $this->assertNull(LeadContact::parse('иван'));
        $this->assertNull(LeadContact::parse(''));
    }
}
