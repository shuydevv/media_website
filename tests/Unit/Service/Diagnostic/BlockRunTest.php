<?php

namespace Tests\Unit\Service\Diagnostic;

use App\Service\Diagnostic\BlockRun;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Все пути блока из таблицы ТЗ (раздел 5.2) — других нет. */
class BlockRunTest extends TestCase
{
    public static function paths(): array
    {
        // путь (уровень + верно/неверно) → итог, вопросов
        return [
            'Л✓ С✓ Сл✓ Сл✓' => ['1+ 2+ 3+ 3+', 3],
            'Л✓ С✓ Сл✓ Сл✗' => ['1+ 2+ 3+ 3-', 3],
            'Л✓ С✓ Сл✗ Сл✓' => ['1+ 2+ 3- 3+', 3],
            'Л✓ С✓ Сл✗ Сл✗' => ['1+ 2+ 3- 3-', 2],
            'Л✓ С✗ С✓ Сл✓' => ['1+ 2- 2+ 3+', 3],
            'Л✓ С✗ С✓ Сл✗' => ['1+ 2- 2+ 3-', 2],
            'Л✓ С✗ С✗' => ['1+ 2- 2-', 1],
            'Л✗ Л✓ С✓ Сл✓' => ['1- 1+ 2+ 3+', 3],
            'Л✗ Л✓ С✓ Сл✗' => ['1- 1+ 2+ 3-', 2],
            'Л✗ Л✓ С✗ С✓' => ['1- 1+ 2- 2+', 2],
            'Л✗ Л✓ С✗ С✗' => ['1- 1+ 2- 2-', 1],
            'Л✗ Л✗' => ['1- 1-', 0],
        ];
    }

    #[DataProvider('paths')]
    public function test_path_is_walked_and_scored(string $path, int $result): void
    {
        $steps = array_map(fn ($s) => ['level' => (int) $s[0], 'correct' => $s[1] === '+'], explode(' ', $path));

        $answers = [];
        foreach ($steps as $i => $step) {
            $this->assertSame($step['level'], BlockRun::nextLevel($answers), "шаг {$i}: не тот уровень");
            $answers[] = $step;
        }

        $this->assertNull(BlockRun::nextLevel($answers), 'после пути блок должен закрыться');
        $this->assertSame($result, BlockRun::result($answers));
    }

    public function test_basic_path_has_no_retry(): void
    {
        $basic = fn ($answers) => BlockRun::nextLevel($answers, 3, false);
        $this->assertSame(1, $basic([]));
        $this->assertSame(2, $basic([['level' => 1, 'correct' => false]]), 'ошибка блок не закрывает');
        $this->assertSame(3, $basic([['level' => 1, 'correct' => true], ['level' => 2, 'correct' => true]]));
        $this->assertNull($basic([['level' => 1, 'correct' => true], ['level' => 2, 'correct' => true], ['level' => 3, 'correct' => true]]), 'не больше 3 вопросов');
        $this->assertFalse(BlockRun::closedByErrors([['correct' => false], ['correct' => false]], false));
        $this->assertSame(2, BlockRun::correctCount([['correct' => true], ['correct' => false], ['correct' => true]]));
    }

    public function test_two_errors_in_a_row_close_the_block(): void
    {
        $this->assertTrue(BlockRun::closedByErrors([['correct' => true], ['correct' => false], ['correct' => false]]));
        $this->assertFalse(BlockRun::closedByErrors([['correct' => false], ['correct' => true], ['correct' => false], ['correct' => true]]));
    }

    public function test_accuracy_shows_mistakes_even_at_top_level(): void
    {
        $answers = [
            ['level' => 1, 'correct' => true], ['level' => 2, 'correct' => true],
            ['level' => 3, 'correct' => false], ['level' => 3, 'correct' => true],
        ];

        $this->assertSame(3, BlockRun::result($answers));
        $this->assertEqualsWithDelta(6 / 9, BlockRun::accuracy($answers), 0.001);
        $this->assertSame(1.0, BlockRun::accuracy(array_map(fn ($a) => ['correct' => true] + $a, $answers)));
        $this->assertSame(0.0, BlockRun::accuracy([['level' => 1, 'correct' => false], ['level' => 1, 'correct' => false]]));
    }

    public function test_guessed_correct_answers_do_not_count_for_lower_bound(): void
    {
        $answers = [
            ['level' => 1, 'correct' => true, 'guess' => false],
            ['level' => 2, 'correct' => true, 'guess' => false],
            ['level' => 3, 'correct' => true, 'guess' => true],
            ['level' => 3, 'correct' => false, 'guess' => false],
        ];

        $this->assertSame(3, BlockRun::result($answers));
        $this->assertSame(2, BlockRun::result($answers, true));
    }
}
