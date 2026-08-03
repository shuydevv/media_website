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
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Сквозные тесты визарда сдачи домашки/пробника — от создания домашки в БД
 * до прохождения студентом и (для ручных заданий) проверки куратором.
 *
 * В проекте нет отдельной тестовой БД (см. комментарий в BillingServiceTest) —
 * тесты идут на той же MySQL, что и dev-окружение, submissions/homeworks
 * тоже не в транзакции. Поэтому здесь тот же приём: всё созданное — с
 * "ТЕСТ (...)" в названии и полной ручной очисткой в tearDown(), в порядке
 * от зависимых записей к родительским.
 */
class HomeworkSubmissionFlowTest extends TestCase
{
    /** @var int[] */
    private array $courseIds = [];

    /** @var int[] */
    private array $userIds = [];

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        if ($this->courseIds !== []) {
            $homeworkIds = Homework::whereIn('course_id', $this->courseIds)->pluck('id');
            Submission::whereIn('homework_id', $homeworkIds)->delete();
            HomeworkTask::whereIn('homework_id', $homeworkIds)->delete();
            Homework::whereIn('id', $homeworkIds)->delete();

            $sessionIds = CourseSession::whereIn('course_id', $this->courseIds)->pluck('id');
            Lesson::whereIn('course_session_id', $sessionIds)->delete();
            CourseSession::whereIn('id', $sessionIds)->delete();

            CourseUser::whereIn('course_id', $this->courseIds)->delete();
            Course::whereIn('id', $this->courseIds)->forceDelete();
        }

        if ($this->userIds !== []) {
            User::whereIn('id', $this->userIds)->delete();
        }

        parent::tearDown();
    }

    private function makeStudent(): User
    {
        $user = User::factory()->create(['role' => User::ROLE_READER]);
        $this->userIds[] = $user->id;
        return $user;
    }

    private function makeMentor(): User
    {
        $user = User::factory()->create(['role' => User::ROLE_MENTOR]);
        $this->userIds[] = $user->id;
        return $user;
    }

    private function makeCourse(): Course
    {
        $course = Course::create([
            'title' => 'ТЕСТ (авто-тест HomeworkSubmissionFlowTest, безопасно удалять)',
            'description' => 'Тест',
            'price_cents' => 100000,
        ]);
        $this->courseIds[] = $course->id;
        return $course;
    }

    private function enroll(User $user, Course $course, ?Carbon $enrolledAt = null): void
    {
        CourseUser::create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'status' => 'active',
            // По умолчанию — явно "давно": иначе enrolled_at=null падает на
            // created_at=now(), которое оказывается ПОЗЖЕ искусственно
            // состаренного due_at из overdue-теста, и Homework::isOverdueFor()
            // решает, что дедлайн прошёл ещё до зачисления (см. комментарий там).
            'enrolled_at' => $enrolledAt ?? now()->subMonth(),
        ]);
    }

    /**
     * Урок привязан к сессии в прошлом — иначе Homework::isLessonUpcoming()
     * прячет домашку целиком (404), см. SubmissionController::create().
     */
    private function makeLesson(Course $course): Lesson
    {
        $session = CourseSession::create([
            'course_id' => $course->id,
            'date' => now()->subDay()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:30:00',
        ]);

        return Lesson::create([
            'course_session_id' => $session->id,
            'title' => 'Тестовый урок',
        ]);
    }

    private function makeHomework(Course $course, Lesson $lesson, array $overrides = []): Homework
    {
        return Homework::create(array_merge([
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'title' => 'Тестовая домашка',
            'type' => 'homework',
            'due_at' => now()->addDays(3),
            'attempts_allowed' => 2,
        ], $overrides));
    }

    private function makeAutoTask(Homework $homework, int $order, string $answer, int $maxScore): HomeworkTask
    {
        return HomeworkTask::create([
            'homework_id' => $homework->id,
            'type' => 'test',
            'question_text' => "Вопрос {$order}",
            'answer' => $answer,
            'max_score' => $maxScore,
            'order' => $order,
        ]);
    }

    private function makeManualTask(Homework $homework, int $order, int $maxScore): HomeworkTask
    {
        return HomeworkTask::create([
            'homework_id' => $homework->id,
            'type' => 'written',
            'question_text' => "Развёрнутый вопрос {$order}",
            'answer' => 'Образцовый ответ',
            'max_score' => $maxScore,
            'order' => $order,
        ]);
    }

    /** @test */
    public function full_auto_homework_on_time_awards_full_corm_and_marks_checked()
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $lesson = $this->makeLesson($course);
        $this->enroll($student, $course);

        $homework = $this->makeHomework($course, $lesson);
        $t1 = $this->makeAutoTask($homework, 1, '12', 2);
        $t2 = $this->makeAutoTask($homework, 2, '123', 3);

        $this->actingAs($student)->get(route('student.submissions.create', $homework));

        $submission = Submission::where('homework_id', $homework->id)->where('user_id', $student->id)->firstOrFail();

        // Вопрос 1 — полностью верно (score = max = 2).
        $this->actingAs($student)
            ->post(route('student.submissions.question.check', [$submission, 1]), ['answer' => '12'])
            ->assertRedirect(route('student.submissions.question', [$submission, 2]));

        $student->refresh();
        $this->assertSame(2, (int) $student->fish_corm_balance, 'Корм за первое верно отвеченное задание не начислен');

        // Вопрос 2 — полностью верно (score = max = 3).
        $this->actingAs($student)
            ->post(route('student.submissions.question.check', [$submission, 2]), ['answer' => '123'])
            ->assertRedirect(route('student.submissions.finish', $submission));

        $student->refresh();
        $this->assertSame(5, (int) $student->fish_corm_balance, 'Корм за второе задание не добавился к первому');

        $this->actingAs($student)
            ->post(route('student.submissions.finish.submit', $submission))
            ->assertRedirect(route('student.submissions.show', $submission));

        $submission->refresh();
        $this->assertSame('checked', $submission->status);
        $this->assertSame(5, (int) $submission->total_score);
        $this->assertSame(5, (int) $submission->autocheck_score);

        $student->refresh();
        // 5 (баллы за задания) + 5 (бонус за вовремя) — флэта за сам факт
        // сдачи и бонуса за первую домашку больше нет.
        $this->assertSame(10, (int) $student->fish_corm_balance);
        $this->assertSame(0, (int) $student->fish_total_fed, 'Начисление корма не должно само по себе кормить рыбу');
    }

    /** @test */
    public function homework_with_manual_task_awaits_mentor_review_then_finalizes_with_task_corm()
    {
        $student = $this->makeStudent();
        $mentor = $this->makeMentor();
        $course = $this->makeCourse();
        $lesson = $this->makeLesson($course);
        $this->enroll($student, $course);

        $homework = $this->makeHomework($course, $lesson);
        $auto = $this->makeAutoTask($homework, 1, '12', 2);
        $manual = $this->makeManualTask($homework, 2, 3);

        $this->actingAs($student)->get(route('student.submissions.create', $homework));
        $submission = Submission::where('homework_id', $homework->id)->where('user_id', $student->id)->firstOrFail();

        $this->actingAs($student)
            ->post(route('student.submissions.question.check', [$submission, 1]), ['answer' => '12']);

        // Ручное задание — check() отвечает 404 (не авто-проверяемое),
        // сохраняется только через save().
        $this->actingAs($student)
            ->post(route('student.submissions.question.check', [$submission, 2]), ['answer' => 'мой развёрнутый ответ'])
            ->assertNotFound();

        $this->actingAs($student)
            ->post(route('student.submissions.question.save', [$submission, 2]), ['answer' => 'мой развёрнутый ответ'])
            ->assertRedirect(route('student.submissions.finish', $submission));

        $this->actingAs($student)->post(route('student.submissions.finish.submit', $submission));

        $submission->refresh();
        $this->assertSame('pending', $submission->status, 'Домашка с непустым ручным заданием должна ждать куратора');
        $this->assertSame(2, (int) $submission->autocheck_score);

        $student->refresh();
        // 2 (авто-балл) + 5 (вовремя) — ручное задание пока не оценено,
        // корма за него ещё нет.
        $this->assertSame(7, (int) $student->fish_corm_balance);

        // Куратор берёт работу в проверку (это же выставляет lock).
        $this->actingAs($mentor)
            ->get(route('mentor.submissions.show', $submission))
            ->assertOk();

        $this->actingAs($mentor)
            ->post(route('mentor.review.task.save', [$submission, $manual->id]), ['score' => 3])
            ->assertRedirect();

        $this->actingAs($mentor)
            ->post(route('mentor.review.finish', $submission))
            ->assertRedirect(route('mentor.submissions.index'));

        $submission->refresh();
        $this->assertSame('checked', $submission->status);
        $this->assertSame(3, (int) $submission->manual_score);
        $this->assertSame(5, (int) $submission->total_score);

        $student->refresh();
        // +3 за ручное задание, начисленные ровно на finalizeSubmission().
        $this->assertSame(10, (int) $student->fish_corm_balance);
    }

    /**
     * Куратор пытается поставить за письменное задание больше, чем его
     * max_score (3) — ожидаем, что оценка клампится сервером, а не просто
     * доверяется присланному числу.
     *
     * @test
     */
    public function mentor_cannot_award_more_than_the_task_max_score()
    {
        $student = $this->makeStudent();
        $mentor = $this->makeMentor();
        $course = $this->makeCourse();
        $lesson = $this->makeLesson($course);
        $this->enroll($student, $course);

        $homework = $this->makeHomework($course, $lesson);
        $manual = $this->makeManualTask($homework, 1, 3);

        $this->actingAs($student)->get(route('student.submissions.create', $homework));
        $submission = Submission::where('homework_id', $homework->id)->where('user_id', $student->id)->firstOrFail();

        $this->actingAs($student)
            ->post(route('student.submissions.question.save', [$submission, 1]), ['answer' => 'мой развёрнутый ответ']);

        $this->actingAs($mentor)->get(route('mentor.submissions.show', $submission));
        $this->actingAs($mentor)
            ->post(route('mentor.review.task.save', [$submission, $manual->id]), ['score' => 999]);

        $submission->refresh();
        $saved = (int) ($submission->per_task_results[(string) $manual->id]['score'] ?? -1);

        $this->assertLessThanOrEqual(3, $saved, 'Оценка куратора должна клампиться до max_score задания (3), а не приниматься как есть');
    }

    /** @test */
    public function overdue_homework_is_marked_expired_and_earns_no_completion_bonus()
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $lesson = $this->makeLesson($course);
        $this->enroll($student, $course);

        $homework = $this->makeHomework($course, $lesson, ['due_at' => now()->subDay()]);
        $this->makeAutoTask($homework, 1, '12', 2);

        $this->actingAs($student)->get(route('student.submissions.create', $homework));
        $submission = Submission::where('homework_id', $homework->id)->where('user_id', $student->id)->firstOrFail();

        $this->actingAs($student)
            ->post(route('student.submissions.question.check', [$submission, 1]), ['answer' => '12']);

        $student->refresh();
        $this->assertSame(2, (int) $student->fish_corm_balance, 'Корм за верный ответ на задание не зависит от срока и должен начисляться всегда');

        $this->actingAs($student)->post(route('student.submissions.finish.submit', $submission));

        $submission->refresh();
        $this->assertSame('expired', $submission->status);

        $student->refresh();
        // Только 2 (балл за задание) — просрочка не даёт вообще ничего
        // сверх баллов за задания (нет ни флэта, ни бонуса за вовремя).
        $this->assertSame(2, (int) $student->fish_corm_balance);
    }

    /** @test */
    public function homework_due_before_enrollment_is_not_marked_overdue()
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $lesson = $this->makeLesson($course);

        // Дедлайн — от прошлого потока, задолго до того, как этот студент
        // вообще подключился к курсу.
        $homework = $this->makeHomework($course, $lesson, ['due_at' => now()->subWeek()]);
        $this->makeAutoTask($homework, 1, '12', 2);

        // Зачисление происходит ПОСЛЕ дедлайна — это и есть проверяемый случай.
        $this->enroll($student, $course, now());

        $rows = $this->actingAs($student)
            ->get(route('student.homeworks.index'))
            ->assertOk()
            ->viewData('rows');

        $row = $rows->firstWhere(fn ($r) => $r['homework']->id === $homework->id);
        $this->assertNotNull($row, 'Домашка должна быть видна студенту (урок уже прошёл)');
        $this->assertSame(
            'not_started',
            $row['status'],
            'Домашка с дедлайном до зачисления студента не должна отмечаться как просроченная'
        );

        // И тот же принцип должен соблюдаться при фактической сдаче — не
        // штрафуем как за опоздание то, что случилось до подключения студента.
        $this->actingAs($student)->get(route('student.submissions.create', $homework));
        $submission = Submission::where('homework_id', $homework->id)->where('user_id', $student->id)->firstOrFail();

        $this->actingAs($student)
            ->post(route('student.submissions.question.check', [$submission, 1]), ['answer' => '12']);
        $this->actingAs($student)->post(route('student.submissions.finish.submit', $submission));

        $submission->refresh();
        $this->assertSame('checked', $submission->status, 'Сдача не должна помечаться "expired" из-за дедлайна, наступившего до зачисления');
    }

    /** @test */
    public function mock_exam_hides_feedback_on_wrong_answer_and_auto_finishes_after_timer_expires()
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $lesson = $this->makeLesson($course);
        $this->enroll($student, $course);

        $homework = $this->makeHomework($course, $lesson, ['type' => 'mock', 'mock_number' => 1]);
        $this->makeAutoTask($homework, 1, '12', 2);

        $this->actingAs($student)->get(route('student.submissions.create', $homework));
        $submission = Submission::where('homework_id', $homework->id)->where('user_id', $student->id)->firstOrFail();

        $this->assertNotNull($submission->started_at, 'У пробника должен быть выставлен таймер (started_at)');
        $this->assertNotNull($submission->expires_at, 'У пробника должен быть выставлен таймер (expires_at)');

        // Неверный ответ на пробнике — check() всё равно должен сохранить и
        // продвинуть вперёд (без вопроса "уверены?"/показа результата),
        // в отличие от обычной домашки.
        $this->actingAs($student)
            ->post(route('student.submissions.question.check', [$submission, 1]), ['answer' => '99'])
            ->assertRedirect(route('student.submissions.finish', $submission));

        $submission->refresh();
        $this->assertArrayHasKey($this->firstTaskId($homework), $submission->answers, 'Ответ на пробнике должен сохраняться даже если он неверный');

        // Двигаем время за expires_at и повторно стучимся в визард —
        // реактивное автозавершение (без крона) должно сработать.
        Carbon::setTestNow($submission->expires_at->copy()->addMinute());

        $this->actingAs($student)
            ->get(route('student.submissions.finish', $submission));

        $submission->refresh();
        $this->assertNotSame('in_progress', $submission->status, 'Просроченный по таймеру пробник должен завершиться реактивно при следующем обращении');
    }

    private function firstTaskId(Homework $homework): int
    {
        return HomeworkTask::where('homework_id', $homework->id)->orderBy('order')->firstOrFail()->id;
    }

    /** @test */
    public function attempts_limit_blocks_creating_a_new_submission_once_exhausted()
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $lesson = $this->makeLesson($course);
        $this->enroll($student, $course);

        $homework = $this->makeHomework($course, $lesson, ['attempts_allowed' => 1]);
        $this->makeAutoTask($homework, 1, '12', 2);

        $this->actingAs($student)->get(route('student.submissions.create', $homework));
        $submission = Submission::where('homework_id', $homework->id)->where('user_id', $student->id)->firstOrFail();
        $this->actingAs($student)->post(route('student.submissions.question.check', [$submission, 1]), ['answer' => '12']);
        $this->actingAs($student)->post(route('student.submissions.finish.submit', $submission));

        // Без ?retry=1 контроллер молча показывает существующую попытку (это
        // штатный путь для прямого перехода по ссылке урока) — ошибка про
        // исчерпанный лимит показывается только при явном запросе на реванш,
        // см. кнопку "Перерешать работу" (submissions/show.blade.php).
        $silent = $this->actingAs($student)->get(route('student.submissions.create', $homework));
        $silent->assertRedirect(route('student.submissions.show', $submission));
        $silent->assertSessionDoesntHaveErrors();

        $response = $this->actingAs($student)->get(route('student.submissions.create', ['homework' => $homework, 'retry' => 1]));
        $response->assertRedirect(route('student.submissions.show', $submission));
        $response->assertSessionHasErrors('attempts');

        $this->assertSame(1, Submission::where('homework_id', $homework->id)->count(), 'Не должно быть создано второй попытки сверх лимита');
    }

    /** @test */
    public function revisiting_an_answered_question_adjusts_corm_by_the_score_delta_not_double_awards()
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $lesson = $this->makeLesson($course);
        $this->enroll($student, $course);

        $homework = $this->makeHomework($course, $lesson);
        $this->makeAutoTask($homework, 1, '123', 3);

        $this->actingAs($student)->get(route('student.submissions.create', $homework));
        $submission = Submission::where('homework_id', $homework->id)->where('user_id', $student->id)->firstOrFail();

        // Полностью верно — score = max = 3.
        $this->actingAs($student)->post(route('student.submissions.question.check', [$submission, 1]), ['answer' => '123']);
        $student->refresh();
        $this->assertSame(3, (int) $student->fish_corm_balance);

        // Пересохраняем тот же (полностью верный) ответ через save() —
        // баланс не должен вырасти повторно (дельта 0).
        $this->actingAs($student)->post(route('student.submissions.question.save', [$submission, 1]), ['answer' => '123']);
        $student->refresh();
        $this->assertSame(3, (int) $student->fish_corm_balance, 'Повторное сохранение того же результата не должно задваивать корм');

        // Меняем ответ на частично верный (score=1 при max=3, см. AutoGrader) —
        // баланс должен скорректироваться на дельту (в минус), а не остаться прежним.
        $this->actingAs($student)->post(route('student.submissions.question.save', [$submission, 1]), ['answer' => '124']);
        $student->refresh();
        $this->assertSame(1, (int) $student->fish_corm_balance, 'Корм должен подстроиться под текущий (уменьшившийся) балл, а не остаться от первого ответа');
    }
}
