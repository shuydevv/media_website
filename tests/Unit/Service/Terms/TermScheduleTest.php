<?php

namespace Tests\Unit\Service\Terms;

use App\Service\Terms\TermSchedule;
use PHPUnit\Framework\TestCase;

// Чистая логика: намеренно PHPUnit\Framework\TestCase, а не Tests\TestCase —
// приложение и база не поднимаются.
class TermScheduleTest extends TestCase
{
    private function config(): array
    {
        return require __DIR__.'/../../../../config/terms.php';
    }

    private function schedule(): TermSchedule
    {
        return new TermSchedule($this->config()['intervals']);
    }

    /** @test */
    public function a_learned_term_is_due_the_next_day()
    {
        $this->assertSame(
            ['step' => 1, 'due_on' => '2026-10-12'],
            $this->schedule()->afterLearning('2026-10-11')
        );
    }

    /** @test */
    public function success_walks_the_grid_and_stops_at_the_ceiling()
    {
        $schedule = $this->schedule();
        $today = '2026-10-01';
        $step = 1;
        $dueDates = [];

        for ($i = 0; $i < 6; $i++) {
            $result = $schedule->afterReview($step, true, $today);
            $step = $result['step'];
            $dueDates[] = $result['due_on'];
            $today = $result['due_on'];
        }

        // 3 → 7 → 14 → 30 дней, затем каждые 30.
        $this->assertSame(
            ['2026-10-04', '2026-10-11', '2026-10-25', '2026-11-24', '2026-12-24', '2027-01-23'],
            $dueDates
        );
        $this->assertSame(5, $step);
    }

    /** @test */
    public function an_error_rolls_back_one_step_but_not_below_the_first()
    {
        $schedule = $this->schedule();

        $this->assertSame(['step' => 3, 'due_on' => '2026-10-18'], $schedule->afterReview(4, false, '2026-10-11'));
        $this->assertSame(['step' => 1, 'due_on' => '2026-10-12'], $schedule->afterReview(1, false, '2026-10-11'));
    }

    /** @test */
    public function two_errors_in_a_row_roll_back_two_steps_not_to_the_start()
    {
        $schedule = $this->schedule();

        $first = $schedule->afterReview(5, false, '2026-10-11');
        $second = $schedule->afterReview($first['step'], false, $first['due_on']);

        $this->assertSame(3, $second['step']);
    }

    /** @test */
    public function a_late_review_counts_from_today_and_is_not_penalised()
    {
        // Срок был две недели назад, повторил только сегодня и верно.
        $this->assertSame(
            ['step' => 3, 'due_on' => '2026-10-18'],
            $this->schedule()->afterReview(2, true, '2026-10-11')
        );
    }

    /** @test */
    public function methods_alternate()
    {
        $schedule = $this->schedule();

        $this->assertSame('b', $schedule->nextMethod('a'));
        $this->assertSame('a', $schedule->nextMethod('b'));
    }

    /**
     * Ученик без пропусков и ошибок: порция в день, все повторения в срок.
     * Проверяет то, на чём стоит выбор потолка в конфиге — он в него не
     * упирается, иначе новые термины блокировались бы даже у идеального
     * ученика.
     *
     * @test
     */
    public function a_perfect_student_never_exceeds_the_daily_review_limit()
    {
        $config = $this->config();
        $schedule = $this->schedule();

        $terms = [];      // [step, due_on]
        $remaining = 190;
        $peak = 0;
        $day = new \DateTimeImmutable('2026-10-01');

        for ($i = 0; $i < 200; $i++) {
            $today = $day->format('Y-m-d');

            $dueToday = 0;
            foreach ($terms as $k => [$step, $dueOn]) {
                if ($dueOn <= $today) {
                    $dueToday++;
                    $result = $schedule->afterReview($step, true, $today);
                    $terms[$k] = [$result['step'], $result['due_on']];
                }
            }
            $peak = max($peak, $dueToday);

            for ($n = min($config['batch_size'], $remaining); $n > 0; $n--, $remaining--) {
                $result = $schedule->afterLearning($today);
                $terms[] = [$result['step'], $result['due_on']];
            }

            $day = $day->modify('+1 day');
        }

        $this->assertSame(0, $remaining);
        $this->assertLessThanOrEqual($config['daily_review_limit'], $peak);
    }
}
