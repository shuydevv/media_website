<?php

namespace Tests\Unit\Service\Trainers;

use App\Service\Trainers\DailyPlanner;
use App\Service\Trainers\Trainer;
use PHPUnit\Framework\TestCase;

class DailyPlannerTest extends TestCase
{
    private function c(string $key, string $situation, int $count, float $sec = 10, array $extra = []): array
    {
        return $extra + [
            'key' => $key,
            'hall' => 'social',
            'situation' => $situation,
            'kind' => in_array($situation, [Trainer::NEW, Trainer::NEW_NEAR_LEVEL], true) ? 'learn' : 'review',
            'count' => $count,
            'sec' => $sec,
            'min' => 3,
            'last' => null,
        ];
    }

    private function plan(array $candidates, int $budget = 600, int $slots = 2): array
    {
        return (new DailyPlanner())->plan($candidates, $budget, $slots, 2);
    }

    /** @test */
    public function takes_only_as_many_trainers_as_slots_most_urgent_first()
    {
        $items = $this->plan([
            $this->c('new', Trainer::NEW, 5),
            $this->c('overdue', Trainer::OVERDUE, 5),
            $this->c('unfinished', Trainer::UNFINISHED, 5),
            $this->c('done', Trainer::DONE, 0),
        ]);

        $this->assertSame(['unfinished', 'overdue'], array_column($items, 'key'));
    }

    /** @test */
    public function splits_budget_and_caps_review_by_pace()
    {
        // 600 с на двоих: по 300 с; при 20 с на карточку — 15 из 20.
        $items = $this->plan([
            $this->c('a', Trainer::REVIEW, 20, 20),
            $this->c('b', Trainer::REVIEW, 20, 20, ['hall' => 'history']),
        ]);

        $this->assertSame([15, 15], array_column($items, 'quota'));
    }

    /** @test */
    public function leftover_from_a_small_trainer_goes_to_the_other()
    {
        // У «a» дела на 50 с — остальные 550 с достаются «b»: 27 карточек.
        $items = $this->plan([
            $this->c('a', Trainer::REVIEW, 5, 10),
            $this->c('b', Trainer::REVIEW, 40, 20, ['hall' => 'history']),
        ]);

        $this->assertSame([5, 27], array_column($items, 'quota'));
    }

    /** @test */
    public function never_less_than_the_minimum_session()
    {
        $items = $this->plan([$this->c('a', Trainer::REVIEW, 20, 60)], 60, 2);

        $this->assertSame(3, $items[0]['quota']);
    }

    /** @test */
    public function unfinished_session_goes_in_whole()
    {
        $items = $this->plan([
            $this->c('a', Trainer::UNFINISHED, 18, 20),
            $this->c('b', Trainer::REVIEW, 20, 10),
        ], 300);

        $this->assertSame(18, $items[0]['quota']);
        // Бюджет съеден недоделанной — второму только минимум.
        $this->assertSame(3, $items[1]['quota']);
    }

    /** @test */
    public function no_new_cards_while_review_backlog_is_big()
    {
        // Повторения на 3 × 20 × 20 = 1200 с > 2 × 300 с.
        $items = $this->plan([
            $this->c('a', Trainer::REVIEW, 20, 20),
            $this->c('b', Trainer::REVIEW, 20, 20),
            $this->c('c', Trainer::REVIEW, 20, 20),
            $this->c('new', Trainer::NEW_NEAR_LEVEL, 5, 60, ['hall' => 'history']),
        ], 300, 3);

        $this->assertNotContains('new', array_column($items, 'key'));
    }

    /** @test */
    public function each_hall_gets_a_trainer_when_there_are_two_courses()
    {
        $items = $this->plan([
            $this->c('terms', Trainer::OVERDUE, 10),
            $this->c('statements', Trainer::OVERDUE, 10),
            $this->c('tasks', Trainer::REVIEW, 10),
            $this->c('dates', Trainer::NEW, 5, 10, ['hall' => 'history']),
        ], 900, 3);

        $this->assertSame(['terms', 'statements', 'dates'], array_column($items, 'key'));
    }

    /** @test */
    public function equal_urgency_rotates_to_the_one_not_planned_longest()
    {
        $items = $this->plan([
            $this->c('yesterday', Trainer::REVIEW, 10, 10, ['last' => '2026-11-21']),
            $this->c('long_ago', Trainer::REVIEW, 10, 10, ['last' => '2026-11-15']),
            $this->c('never', Trainer::REVIEW, 10, 10),
        ]);

        $this->assertSame(['never', 'long_ago'], array_column($items, 'key'));
    }

    /** @test */
    public function estimates_seconds_per_item()
    {
        $items = $this->plan([$this->c('a', Trainer::REVIEW, 10, 12)]);

        $this->assertSame(120, $items[0]['sec']);
    }
}
