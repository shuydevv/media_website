<?php

namespace Tests\Unit\Service\Homework;

use App\Models\HomeworkTask;
use App\Service\Homework\AutoGrader;
use Tests\TestCase;

class AutoGraderTest extends TestCase
{
    private function task(array $attrs): HomeworkTask
    {
        $task = new HomeworkTask();
        $task->fill($attrs);
        if (isset($attrs['id'])) {
            $task->id = $attrs['id'];
        }
        return $task;
    }

    /** @test */
    public function exact_match_gets_full_score()
    {
        $grader = new AutoGrader();
        $task = $this->task(['type' => 'text', 'answer' => 'кит', 'max_score' => 3]);

        $result = $grader->scoreOne($task, 'кит');

        $this->assertSame(3, $result['score']);
        $this->assertSame('ok', $result['status']);
    }

    /** @test */
    public function comparison_is_case_and_whitespace_insensitive()
    {
        $grader = new AutoGrader();
        $task = $this->task(['type' => 'text', 'answer' => 'кит', 'max_score' => 3]);

        $result = $grader->scoreOne($task, '  КИТ  ');

        $this->assertSame(3, $result['score']);
        $this->assertSame('ok', $result['status']);
    }

    /** @test */
    public function yo_and_ye_are_treated_as_the_same_letter()
    {
        $grader = new AutoGrader();
        $task = $this->task(['type' => 'text', 'answer' => 'ёлка', 'max_score' => 3]);

        $result = $grader->scoreOne($task, 'елка');

        $this->assertSame(3, $result['score']);
        $this->assertSame('ok', $result['status']);
    }

    /** @test */
    public function empty_answer_against_nonempty_correct_scores_zero()
    {
        $grader = new AutoGrader();
        $task = $this->task(['type' => 'text', 'answer' => 'привет', 'max_score' => 5]);

        $result = $grader->scoreOne($task, '');

        $this->assertSame(0, $result['score']);
        $this->assertSame('fail', $result['status']);
        // errorsEmpty() = max(1, strlen(correct)) — здесь строка длиной 6.
        $this->assertSame(6, $result['errors']);
    }

    /** @test */
    public function task_without_a_correct_answer_never_penalizes_the_student()
    {
        $grader = new AutoGrader();
        $task = $this->task(['type' => 'text', 'answer' => null, 'max_score' => 5]);

        $result = $grader->scoreOne($task, 'что угодно');

        $this->assertSame(0, $result['score']);
        $this->assertSame(0, $result['errors']);
    }

    /**
     * max_score=1 — из scoreByErrors: любая ошибка обнуляет балл целиком,
     * частичных баллов при max=1 не бывает.
     *
     * @test
     */
    public function max_score_one_has_no_partial_credit()
    {
        $grader = new AutoGrader();
        $task = $this->task(['type' => 'text', 'answer' => 'а', 'max_score' => 1]);

        $this->assertSame(1, $grader->scoreOne($task, 'а')['score']);
        $this->assertSame(0, $grader->scoreOne($task, 'б')['score']);
    }

    /**
     * max_score=2 — из scoreByErrors: 1 ошибка ещё даёт 1 балл, 2+ ошибки — 0.
     *
     * @test
     */
    public function max_score_two_gives_partial_credit_only_for_a_single_error()
    {
        $grader = new AutoGrader();
        $task = $this->task(['type' => 'text', 'answer' => 'да', 'max_score' => 2]);

        $this->assertSame(2, $grader->scoreOne($task, 'да')['score']);   // 0 ошибок
        $this->assertSame(1, $grader->scoreOne($task, 'до')['score']);   // 1 ошибка
        $this->assertSame(0, $grader->scoreOne($task, 'пи')['score']);   // 2 ошибки
    }

    /**
     * max_score=3 — из scoreByErrors: score = max(0, 3 - errors).
     *
     * @test
     */
    public function max_score_three_subtracts_errors_directly()
    {
        $grader = new AutoGrader();
        $task = $this->task(['type' => 'text', 'answer' => 'кит', 'max_score' => 3]);

        $this->assertSame(3, $grader->scoreOne($task, 'кит')['score']);   // 0 ошибок
        $this->assertSame(2, $grader->scoreOne($task, 'кир')['score']);   // 1 ошибка
        $this->assertSame(1, $grader->scoreOne($task, 'фыт')['score']);   // 2 ошибки
        $this->assertSame(0, $grader->scoreOne($task, 'дуб')['score']);   // 3 ошибки
    }

    /**
     * max_score > 3 (ветка "else" в scoreByErrors) — та же формула
     * max(0, max - errors), но с большим потолком.
     *
     * @test
     */
    public function max_score_above_three_uses_the_generic_subtraction_branch()
    {
        $grader = new AutoGrader();
        $task = $this->task(['type' => 'text', 'answer' => 'книга', 'max_score' => 5]);

        $result = $grader->scoreOne($task, 'кнюгя'); // 2 несовпадающие буквы

        $this->assertSame(3, $result['score']);
        $this->assertSame('partial', $result['status']);
    }

    /**
     * Для типов matching/table порядок символов важен — переставленные
     * местами, но идентичные по составу буквы должны считаться ошибкой.
     * Для обычного типа тот же ввод даёт полный балл (сравнение по составу).
     *
     * @test
     */
    public function order_matters_for_matching_and_table_types()
    {
        $grader = new AutoGrader();

        $unordered = $this->task(['type' => 'text', 'answer' => 'abc', 'max_score' => 3]);
        $ordered = $this->task(['type' => 'matching', 'answer' => 'abc', 'max_score' => 3]);

        // Тот же набор букв, другой порядок.
        $this->assertSame(3, $grader->scoreOne($unordered, 'acb')['score']);
        $this->assertLessThan(3, $grader->scoreOne($ordered, 'acb')['score']);
    }

    /**
     * order_matters=true как явный флаг задачи (не только по типу) тоже
     * должен переключать на позиционное сравнение.
     *
     * @test
     */
    public function explicit_order_matters_flag_is_respected()
    {
        $grader = new AutoGrader();
        $task = $this->task(['type' => 'text', 'answer' => 'abc', 'max_score' => 3, 'order_matters' => true]);

        $this->assertLessThan(3, $grader->scoreOne($task, 'acb')['score']);
    }

    /**
     * image_auto: если в ответе есть буквы, применяется точное сравнение
     * "всё или ничего" вместо посимвольного диффа.
     *
     * @test
     */
    public function image_auto_with_letters_requires_an_exact_match()
    {
        $grader = new AutoGrader();
        $task = $this->task(['type' => 'image_auto', 'answer' => 'b', 'max_score' => 3]);

        $this->assertSame(3, $grader->scoreOne($task, 'b')['score']);
        $this->assertSame(2, $grader->scoreOne($task, 'c')['score']); // 1 ошибка, scoreByErrors(1, 3) = 3 - 1
    }

    /**
     * gradeWithTasks: задания из HomeworkTask::MANUAL_TYPES не должны
     * попадать в автоматический подсчёт суммы и должны переключать
     * fully_auto в false — это ключевая развилка "checked" vs "pending"
     * во всей цепочке сдачи домашки.
     *
     * @test
     */
    public function manual_tasks_are_excluded_from_the_auto_total_and_flip_fully_auto()
    {
        $grader = new AutoGrader();

        $auto = $this->task(['id' => 1, 'type' => 'text', 'answer' => 'кит', 'max_score' => 3]);
        $manual = $this->task(['id' => 2, 'type' => 'written', 'answer' => null, 'max_score' => 5]);

        $result = $grader->gradeWithTasks([$auto, $manual], [1 => 'кит', 2 => 'развёрнутый ответ']);

        $this->assertFalse($result['fully_auto']);
        $this->assertSame(3, $result['score']); // только автопроверяемое задание вносит вклад
        $this->assertArrayNotHasKey('score', $result['per_task'][2]); // ручное — без авто-оценки
        $this->assertSame(3, $result['per_task'][1]['score']);
    }

    /** @test */
    public function fully_auto_is_true_when_no_manual_tasks_are_present()
    {
        $grader = new AutoGrader();
        $task = $this->task(['id' => 1, 'type' => 'text', 'answer' => 'кит', 'max_score' => 3]);

        $result = $grader->gradeWithTasks([$task], [1 => 'кит']);

        $this->assertTrue($result['fully_auto']);
        $this->assertSame(3, $result['score']);
    }
}
