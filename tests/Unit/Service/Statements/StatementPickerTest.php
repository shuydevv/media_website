<?php

namespace Tests\Unit\Service\Statements;

use App\Service\Statements\StatementPicker;
use PHPUnit\Framework\TestCase;

class StatementPickerTest extends TestCase
{
    private function picker(): StatementPicker
    {
        // Без перемешивания — порядок предсказуем.
        return new StatementPicker(fn (array $items) => $items);
    }

    private function items(int $from, int $count, bool $isTrue, ?int $task = null): array
    {
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = ['id' => $from + $i, 'is_true' => $isTrue, 'task' => $task ?? ($from + $i)];
        }

        return $out;
    }

    /** @test */
    public function due_statements_go_first()
    {
        $due = $this->items(1, 3, true);
        $unseen = array_merge($this->items(100, 20, true), $this->items(200, 20, false));

        $ids = $this->picker()->pick($due, $unseen, [], 15);

        $this->assertCount(15, $ids);
        $this->assertEqualsCanonicalizing([1, 2, 3], array_values(array_intersect($ids, [1, 2, 3])));
    }

    /** @test */
    public function new_statements_balance_true_and_false()
    {
        $unseen = array_merge($this->items(100, 30, true), $this->items(200, 30, false));
        $ids = $this->picker()->pick([], $unseen, [], 15);

        $trues = count(array_filter($ids, fn ($id) => $id < 200));
        $this->assertContains($trues, [7, 8]);
    }

    /** @test */
    public function seen_statements_fill_only_when_new_ones_run_out()
    {
        $unseen = $this->items(100, 4, true);
        $seen = array_merge($this->items(300, 10, false), $this->items(400, 10, true));

        $ids = $this->picker()->pick([], $unseen, $seen, 15);

        $this->assertCount(15, $ids);
        foreach ([100, 101, 102, 103] as $id) {
            $this->assertContains($id, $ids);
        }
    }

    /** @test */
    public function cards_from_one_task_are_not_neighbours_when_possible()
    {
        $unseen = array_merge(
            $this->items(100, 5, true, 1),
            $this->items(200, 5, false, 2),
            $this->items(300, 5, true, 3)
        );
        $byId = [];
        foreach ($unseen as $c) {
            $byId[$c['id']] = $c['task'];
        }

        $ids = (new StatementPicker())->pick([], $unseen, [], 15);
        for ($i = 1; $i < count($ids); $i++) {
            $this->assertNotSame($byId[$ids[$i - 1]], $byId[$ids[$i]], 'соседи из одного задания: '.implode(',', $ids));
        }
    }

    /** @test */
    public function an_empty_pool_gives_an_empty_session()
    {
        $this->assertSame([], $this->picker()->pick([], [], [], 15));
    }
}
