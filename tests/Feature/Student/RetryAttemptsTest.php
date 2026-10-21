<?php

namespace Tests\Feature\Student;

use App\Models\Course;
use App\Models\CourseSession;
use App\Models\CourseUser;
use App\Models\Homework;
use App\Models\HomeworkTask;
use App\Models\Lesson;
use App\Models\Submission;
use App\Models\User;
use Tests\TestCase;

/**
 * Повторные попытки: статус домашки при начатой второй попытке, отмена
 * попытки, «Перерешать ошибки», очередь куратора без устаревших попыток.
 * База — SQLite в памяти (см. phpunit.xml), очистка не нужна.
 */
class RetryAttemptsTest extends TestCase
{
    private function student(): User
    {
        return User::factory()->create(['role' => User::ROLE_READER]);
    }

    private function mentor(): User
    {
        return User::factory()->create(['role' => User::ROLE_MENTOR]);
    }

    private function homework(User $student, array $overrides = []): Homework
    {
        $course = Course::create(['title' => 'Курс', 'description' => 'Тест', 'price_cents' => 100000]);
        CourseUser::create([
            'course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
            'enrolled_at' => now()->subMonth(),
        ]);
        $session = CourseSession::create([
            'course_id' => $course->id,
            'date' => now()->subDay()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:30:00',
        ]);
        $lesson = Lesson::create(['course_session_id' => $session->id, 'title' => 'Урок']);

        return Homework::create(array_merge([
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'title' => 'Домашка',
            'type' => 'homework',
            'due_at' => now()->addDays(3),
            'attempts_allowed' => 2,
        ], $overrides));
    }

    private function autoTask(Homework $hw, int $order, string $answer, int $max = 1): HomeworkTask
    {
        return HomeworkTask::create([
            'homework_id' => $hw->id, 'type' => 'test', 'question_text' => "Вопрос {$order}",
            'answer' => $answer, 'max_score' => $max, 'order' => $order,
        ]);
    }

    private function manualTask(Homework $hw, int $order, int $max = 3): HomeworkTask
    {
        return HomeworkTask::create([
            'homework_id' => $hw->id, 'type' => 'written', 'question_text' => "Развёрнутый {$order}",
            'answer' => 'Образец', 'max_score' => $max, 'order' => $order,
        ]);
    }

    private function start(User $student, Homework $hw, string $query = ''): Submission
    {
        $this->actingAs($student)->get(route('student.submissions.create', $hw) . $query);

        return Submission::where('homework_id', $hw->id)->where('user_id', $student->id)
            ->where('status', 'in_progress')->latest('id')->firstOrFail();
    }

    private function answer(User $student, Submission $s, int $position, string $answer): void
    {
        $this->actingAs($student)->post(route('student.submissions.question.save', [$s, $position]), ['answer' => $answer]);
    }

    private function submit(User $student, Submission $s): Submission
    {
        $this->actingAs($student)->post(route('student.submissions.finish.submit', $s));

        return $s->refresh();
    }

    /** @test */
    public function started_retry_does_not_make_submitted_homework_look_undone()
    {
        $student = $this->student();
        $hw = $this->homework($student);
        $this->autoTask($hw, 1, '12');

        $first = $this->start($student, $hw);
        $this->answer($student, $first, 1, '12');
        $this->submit($student, $first);
        $this->assertSame('checked', $first->status);

        $this->start($student, $hw, '?retry=1');

        $this->actingAs($student)->get(route('student.homeworks.index'))
            ->assertOk()
            ->assertSee('Проверено')
            ->assertSee('Начата попытка № 2')
            ->assertDontSee('Не выполнено');

        $this->actingAs($student)->get(route('student.submissions.show', $first))
            ->assertOk()
            ->assertSee('Попытка № 2 начата, но ещё не отправлена')
            ->assertSee('Отменить попытку');
    }

    /** @test */
    public function retry_can_be_cancelled_and_its_corm_is_taken_back()
    {
        $student = $this->student();
        $hw = $this->homework($student);
        $this->autoTask($hw, 1, '12', 2);

        $first = $this->start($student, $hw);
        $this->answer($student, $first, 1, '12');
        $this->submit($student, $first);
        $balanceBefore = (int) $student->refresh()->fish_corm_balance;

        $retry = $this->start($student, $hw, '?retry=1');
        $this->actingAs($student)->post(route('student.submissions.question.check', [$retry, 1]), ['answer' => '12']);
        $this->assertSame($balanceBefore + 2, (int) $student->refresh()->fish_corm_balance);

        $this->actingAs($student)->post(route('student.submissions.cancel', $retry))
            ->assertRedirect(route('student.submissions.show', $first));

        $this->assertNull(Submission::find($retry->id));
        $this->assertSame($balanceBefore, (int) $student->refresh()->fish_corm_balance, 'Корм за отменённую попытку не списан');

        // Попытка не потрачена: можно начать вторую заново.
        $again = $this->start($student, $hw, '?retry=1');
        $this->assertSame(2, (int) $again->attempt_no);
    }

    /** @test */
    public function first_homework_attempt_cannot_be_cancelled()
    {
        $student = $this->student();
        $hw = $this->homework($student);
        $this->autoTask($hw, 1, '12');

        $first = $this->start($student, $hw);

        $this->actingAs($student)->post(route('student.submissions.cancel', $first))->assertForbidden();
        $this->assertNotNull(Submission::find($first->id));
    }

    /** @test */
    public function mock_can_be_cancelled_only_before_the_first_answer()
    {
        $student = $this->student();
        $mock = $this->homework($student, ['type' => 'mock', 'mock_number' => 1]);
        $this->autoTask($mock, 1, '12');
        $this->autoTask($mock, 2, '34');

        $attempt = $this->start($student, $mock);
        $this->actingAs($student)->get(route('student.submissions.question', [$attempt, 1]))
            ->assertSee('Отменить попытку');

        $this->actingAs($student)->post(route('student.submissions.cancel', $attempt))
            ->assertRedirect(route('student.mocks.index'));
        $this->assertNull(Submission::find($attempt->id));

        $attempt = $this->start($student, $mock);
        $this->answer($student, $attempt, 1, '12');

        $this->actingAs($student)->get(route('student.submissions.question', [$attempt, 2]))
            ->assertDontSee('Отменить попытку');
        $this->actingAs($student)->post(route('student.submissions.cancel', $attempt))->assertForbidden();
        $this->assertNotNull(Submission::find($attempt->id));
    }

    /** @test */
    public function expired_mock_without_answers_is_discarded_not_counted()
    {
        $student = $this->student();
        $mock = $this->homework($student, ['type' => 'mock', 'mock_number' => 1]);
        $this->autoTask($mock, 1, '12');

        $attempt = $this->start($student, $mock);
        $attempt->forceFill(['expires_at' => now()->subMinute()])->save();

        $fresh = $this->start($student, $mock);

        $this->assertNull(Submission::find($attempt->id));
        $this->assertSame(1, (int) $fresh->attempt_no);
        $this->assertSame(0, Submission::where('homework_id', $mock->id)->where('status', '!=', 'in_progress')->count());
    }

    /** @test */
    public function mock_list_keeps_finished_status_while_retry_in_progress()
    {
        $student = $this->student();
        $mock = $this->homework($student, ['type' => 'mock', 'mock_number' => 1]);
        $this->autoTask($mock, 1, '12');

        $first = $this->start($student, $mock);
        $this->answer($student, $first, 1, '12');
        $this->submit($student, $first);
        $this->start($student, $mock, '?retry=1');

        $this->actingAs($student)->get(route('student.mocks.index'))
            ->assertOk()
            ->assertSee('Проверено')
            ->assertSee('Начата попытка № 2');
    }

    /** @test */
    public function retry_mistakes_carries_correct_tasks_and_redoes_only_wrong_ones()
    {
        $student = $this->student();
        $hw = $this->homework($student);
        $this->autoTask($hw, 1, '12', 2);
        $this->autoTask($hw, 2, '34', 2);
        $this->autoTask($hw, 3, '56', 2);

        $first = $this->start($student, $hw);
        $this->answer($student, $first, 1, '12');
        $this->answer($student, $first, 2, '99');
        $this->answer($student, $first, 3, '56');
        $this->submit($student, $first);
        $this->assertSame(4, (int) $first->total_score);

        $this->actingAs($student)->get(route('student.submissions.show', $first))
            ->assertSee('Перерешать ошибки: задание 2')
            ->assertSee('Перерешать всю работу');

        $balance = (int) $student->refresh()->fish_corm_balance;

        $response = $this->actingAs($student)->get(route('student.submissions.create', $hw) . '?retry=1&mode=mistakes');
        $retry = Submission::where('homework_id', $hw->id)->where('status', 'in_progress')->firstOrFail();
        $response->assertRedirect(route('student.submissions.question', [$retry, 2]));

        $this->assertTrue($retry->isTaskCarried($hw->tasks()->where('order', 1)->value('id')));
        $this->assertFalse($retry->isTaskCarried($hw->tasks()->where('order', 2)->value('id')));

        // Навигация — только по ошибкам; пилюля № 1 (перенесённое) не показывается.
        $this->actingAs($student)->get(route('student.submissions.question', [$retry, 2]))
            ->assertSee('Перерешиваем ошибки: исправлено 0 из 1');

        // Перенесённое задание не перезаписывается.
        $this->answer($student, $retry, 1, '00');
        $this->assertSame('12', $retry->refresh()->answers[$hw->tasks()->where('order', 1)->value('id')]);

        $this->actingAs($student)->post(route('student.submissions.question.check', [$retry, 2]), ['answer' => '34']);
        $this->assertSame($balance + 2, (int) $student->refresh()->fish_corm_balance, 'Корм — только за перерешанное задание');

        $retry = $this->submit($student, $retry);
        $this->assertSame('checked', $retry->status);
        $this->assertSame(6, (int) $retry->total_score);
        $this->assertSame(2, (int) $retry->attempt_no);
    }

    /** @test */
    public function retry_mistakes_keeps_mentor_grade_for_correct_written_task()
    {
        $student = $this->student();
        $mentor = $this->mentor();
        $hw = $this->homework($student);
        $auto = $this->autoTask($hw, 1, '12', 2);
        $good = $this->manualTask($hw, 2, 3);
        $bad = $this->manualTask($hw, 3, 3);

        $first = $this->start($student, $hw);
        $this->answer($student, $first, 1, '11');
        $this->answer($student, $first, 2, 'Отличное эссе');
        $this->answer($student, $first, 3, 'Слабое эссе');
        $this->submit($student, $first);

        $this->actingAs($mentor)->get(route('mentor.submissions.show', $first));
        $this->actingAs($mentor)->post(route('mentor.review.task.save', [$first, $good->id]), ['score' => 3, 'comment' => 'Молодец']);
        $this->actingAs($mentor)->post(route('mentor.review.task.save', [$first, $bad->id]), ['score' => 1, 'comment' => 'Доработай аргумент']);
        $this->actingAs($mentor)->post(route('mentor.review.finish', $first));
        $this->assertSame('checked', $first->refresh()->status);

        $this->actingAs($student)->get(route('student.submissions.create', $hw) . '?retry=1&mode=mistakes');
        $retry = Submission::where('homework_id', $hw->id)->where('status', 'in_progress')->firstOrFail();

        $this->assertTrue($retry->isTaskCarried($good->id));
        $this->assertFalse($retry->isTaskCarried($bad->id));
        $this->assertFalse($retry->isTaskCarried($auto->id));

        $this->answer($student, $retry, 1, '12');
        $this->answer($student, $retry, 3, 'Исправленное эссе');
        $retry = $this->submit($student, $retry);

        // Ручное с ошибкой ушло куратору, перенесённое — уже с оценкой.
        $this->assertSame('pending', $retry->status);
        $this->assertSame(3, (int) $retry->per_task_results[$good->id]['score']);
        $this->assertArrayNotHasKey('score', $retry->per_task_results[$bad->id]);

        // Куратор видит прошлую оценку по перерешанному заданию.
        $this->actingAs($mentor)->get(route('mentor.submissions.show', $retry))
            ->assertOk()
            ->assertSee('Прошлая попытка № 1')
            ->assertSee('Доработай аргумент')
            ->assertSee('Из прошлой попытки');
    }

    /** @test */
    public function mentor_queue_drops_older_attempt_once_newer_one_is_submitted_and_checked()
    {
        $student = $this->student();
        $mentor = $this->mentor();
        $hw = $this->homework($student);
        $manual = $this->manualTask($hw, 1, 3);

        $first = $this->start($student, $hw);
        $this->answer($student, $first, 1, 'Эссе 1');
        $this->submit($student, $first);

        $second = $this->start($student, $hw, '?retry=1');
        $this->answer($student, $second, 1, 'Эссе 2');
        $this->submit($student, $second);

        $this->assertSame([$second->id], Submission::pendingReviewQueue()->pluck('id')->all());

        $this->actingAs($mentor)->get(route('mentor.submissions.show', $second));
        $this->actingAs($mentor)->post(route('mentor.review.task.save', [$second, $manual->id]), ['score' => 2]);
        $this->actingAs($mentor)->post(route('mentor.review.finish', $second));

        // Раньше здесь всплывала первая попытка.
        $this->assertSame([], Submission::pendingReviewQueue()->pluck('id')->all());

        $this->actingAs($mentor)->get(route('mentor.submissions.show', $first))
            ->assertSee('Ученик уже сдал более новую попытку');
    }

    /** @test */
    public function mentor_is_warned_when_student_is_redoing_the_work()
    {
        $student = $this->student();
        $mentor = $this->mentor();
        $hw = $this->homework($student);
        $this->manualTask($hw, 1, 3);

        $first = $this->start($student, $hw);
        $this->answer($student, $first, 1, 'Эссе');
        $this->submit($student, $first);
        $this->start($student, $hw, '?retry=1');

        // Незаконченная новая попытка старую из очереди не убирает...
        $this->assertSame([$first->id], Submission::pendingReviewQueue()->pluck('id')->all());

        // ...но куратор видит, что ученик перерешивает.
        $this->actingAs($mentor)->get(route('mentor.submissions.index'))
            ->assertSee('Ученик перерешивает');
        $this->actingAs($mentor)->get(route('mentor.submissions.show', $first))
            ->assertSee('Ученик сейчас перерешивает эту работу');
    }

    /** @test */
    public function result_page_lets_student_switch_between_attempts()
    {
        $student = $this->student();
        $hw = $this->homework($student);
        $this->autoTask($hw, 1, '12');

        $first = $this->start($student, $hw);
        $this->answer($student, $first, 1, '00');
        $this->submit($student, $first);
        $second = $this->start($student, $hw, '?retry=1');
        $this->answer($student, $second, 1, '12');
        $this->submit($student, $second);

        $this->actingAs($student)->get(route('student.submissions.show', $first))
            ->assertOk()
            ->assertSee('Попытки:')
            ->assertSee(route('student.submissions.show', $second), false)
            ->assertDontSee('В зачёте последняя попытка');
    }

    /** @test */
    public function overdue_attempt_without_manual_tasks_shows_score_not_review()
    {
        // Сдана после дедлайна — статус expired, но проверять куратору нечего.
        $student = $this->student();
        $hw = $this->homework($student, ['due_at' => now()->subDay()]);
        $this->autoTask($hw, 1, '12');

        $first = $this->start($student, $hw);
        $this->answer($student, $first, 1, '00');
        $this->submit($student, $first);
        $second = $this->start($student, $hw, '?retry=1');
        $this->answer($student, $second, 1, '12');
        $this->submit($student, $second);
        $this->assertSame('expired', $second->status);

        $this->actingAs($student)->get(route('student.submissions.show', $second))
            ->assertOk()
            ->assertSee('Проверено')
            ->assertDontSee('на проверке');
    }
}
