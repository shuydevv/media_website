<?php

namespace Tests\Unit\Service\Statements;

use App\Service\Statements\StatementSchedule;
use PHPUnit\Framework\TestCase;

class StatementScheduleTest extends TestCase
{
    private function schedule(): StatementSchedule
    {
        return new StatementSchedule([1 => 1, 2 => 3, 3 => 7, 4 => 14, 5 => 30]);
    }

    /** @test */
    public function a_new_statement_answered_right_is_not_repeated()
    {
        $this->assertSame(['step' => 0, 'due_on' => null], $this->schedule()->after(null, null, true, '2026-11-06'));
    }

    /** @test */
    public function any_mistake_comes_back_tomorrow()
    {
        foreach ([[null, null], [0, null], [3, '2026-11-06'], [5, '2026-12-01']] as [$step, $due]) {
            $this->assertSame(['step' => 1, 'due_on' => '2026-11-07'], $this->schedule()->after($step, $due, false, '2026-11-06'));
        }
    }

    /** @test */
    public function a_right_repeat_climbs_the_ladder_and_then_is_learned()
    {
        $s = $this->schedule();
        $this->assertSame(['step' => 2, 'due_on' => '2026-11-09'], $s->after(1, '2026-11-06', true, '2026-11-06'));
        $this->assertSame(['step' => 5, 'due_on' => '2026-12-06'], $s->after(4, '2026-11-06', true, '2026-11-06'));
        $this->assertSame(['step' => 5, 'due_on' => null], $s->after(5, '2026-11-06', true, '2026-11-06'));
    }

    /** @test */
    public function a_learned_statement_answered_right_stays_learned()
    {
        $this->assertSame(['step' => 5, 'due_on' => null], $this->schedule()->after(5, null, true, '2026-11-06'));
    }
}
