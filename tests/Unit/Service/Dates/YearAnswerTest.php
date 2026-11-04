<?php

namespace Tests\Unit\Service\Dates;

use App\Service\Dates\YearAnswer;
use PHPUnit\Framework\TestCase;

class YearAnswerTest extends TestCase
{
    /** @test */
    public function a_single_year_is_its_own_digits()
    {
        $y = new YearAnswer();

        $this->assertSame(['1', '7', '0', '9'], $y->cells(1709, 1709));
        $this->assertSame(['8', '6', '2'], $y->cells(862, 862));
        $this->assertNull($y->splitAt(1709, 1709));
    }

    /** @test */
    public function a_range_is_entered_as_start_then_end()
    {
        $y = new YearAnswer();

        $this->assertSame('17001721', $y->expected(1700, 1721));
        $this->assertSame(4, $y->splitAt(1700, 1721));
        $this->assertTrue($y->matches('1700—1721', 1700, 1721));
        $this->assertFalse($y->matches('1700', 1700, 1721));
    }

    /** @test */
    public function a_current_reign_has_only_a_start()
    {
        $y = new YearAnswer();

        $this->assertSame('2012', $y->expected(2012, null));
        $this->assertTrue($y->matches('2012', 2012, null));
    }

    /** @test */
    public function typos_are_not_forgiven()
    {
        $this->assertFalse((new YearAnswer())->matches('1707', 1709, 1709));
    }

    /** @test */
    public function the_miss_is_the_largest_gap_of_the_two_years()
    {
        $y = new YearAnswer();

        $this->assertSame(2, $y->missBy('1707', 1709, 1709));
        $this->assertSame(3, $y->missBy('17011724', 1700, 1721));
        $this->assertNull($y->missBy('17', 1709, 1709));
    }
}
