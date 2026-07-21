<?php

namespace Tests\Feature\Commands;

use App\Models\Course;
use App\Models\CourseSession;
use App\Models\CourseUser;
use App\Models\Homework;
use App\Models\Lesson;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\HomeworkDueSoonNotification;
use App\Service\EnrollmentService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Нет отдельной тестовой БД, задействованные таблицы MyISAM — ручная очистка, см. BillingServiceTest. */
class NotifyHomeworkDueSoonTest extends TestCase
{
    /** @var int[] */
    private array $createdUserIds = [];

    /** @var int[] */
    private array $createdCourseIds = [];

    /** @var int[] */
    private array $createdSessionIds = [];

    /** @var int[] */
    private array $createdHomeworkIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        // "running it twice" реально гоняет очередь (не через Notification::fake()) —
        // не должно пытаться слать письмо через боевой SMTP из .env.
        config(['mail.default' => 'log']);
    }

    protected function tearDown(): void
    {
        if ($this->createdUserIds !== []) {
            // "running it twice" не использует Notification::fake() (нужен
            // реальный проход через очередь ради дедупа по таблице
            // notifications) — подчищаем то, что она реально туда пишет.
            \Illuminate\Notifications\DatabaseNotification::whereIn('notifiable_id', $this->createdUserIds)->delete();
        }
        \Illuminate\Support\Facades\DB::table('jobs')->truncate();

        if ($this->createdHomeworkIds !== []) {
            Submission::whereIn('homework_id', $this->createdHomeworkIds)->delete();
            Homework::whereIn('id', $this->createdHomeworkIds)->delete();
        }
        if ($this->createdSessionIds !== []) {
            Lesson::whereIn('course_session_id', $this->createdSessionIds)->delete();
            CourseSession::whereIn('id', $this->createdSessionIds)->delete();
        }
        if ($this->createdCourseIds !== []) {
            CourseUser::whereIn('course_id', $this->createdCourseIds)->delete();
            Course::whereIn('id', $this->createdCourseIds)->forceDelete();
        }
        if ($this->createdUserIds !== []) {
            User::whereIn('id', $this->createdUserIds)->delete();
        }
        parent::tearDown();
    }

    /** Курс + записанный ученик + урок, который уже прошёл + домашка со скорым дедлайном. */
    private function makeHomeworkDueSoon(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_READER]);
        $this->createdUserIds[] = $user->id;

        $course = Course::create([
            'title' => 'ТЕСТ (авто-тест NotifyHomeworkDueSoonTest, безопасно удалять)',
            'description' => 'Тест',
            'price_cents' => 100000,
        ]);
        $this->createdCourseIds[] = $course->id;

        app(EnrollmentService::class)->enrollUser($user, $course, ['source' => 'manual']);

        $session = CourseSession::create([
            'course_id' => $course->id,
            'date' => now()->subDay()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 60,
            'status' => 'active',
        ]);
        $this->createdSessionIds[] = $session->id;

        $lesson = Lesson::create(['course_session_id' => $session->id, 'title' => 'Тестовый урок']);

        $homework = Homework::create([
            'title' => 'Тестовая домашка',
            'type' => 'homework',
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'due_at' => now()->addHours(12),
        ]);
        $this->createdHomeworkIds[] = $homework->id;

        return [$user, $course, $homework];
    }

    /** @test */
    public function it_notifies_a_student_with_no_submission_and_a_due_date_within_a_day()
    {
        Notification::fake();
        [$user] = $this->makeHomeworkDueSoon();

        $this->artisan('homeworks:notify-due-soon');

        Notification::assertSentTo($user, HomeworkDueSoonNotification::class);
    }

    /** @test */
    public function running_it_twice_does_not_notify_the_same_homework_again()
    {
        // Дедуп для этого типа устроен иначе, чем у остальных (нет
        // pivot-колонки на уровне "домашка х ученик") — команда сама
        // запрашивает таблицу notifications как источник истины (см.
        // NotifyHomeworkDueSoon). Notification::fake() перехватывает
        // отправку ДО записи в БД, поэтому для проверки именно этого
        // дедупа нужен реальный прогон очереди, а не фейк.
        [$user] = $this->makeHomeworkDueSoon();

        $this->artisan('homeworks:notify-due-soon');
        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true]);
        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true]);

        $firstRunCount = \Illuminate\Notifications\DatabaseNotification::where('notifiable_id', $user->id)
            ->where('type', HomeworkDueSoonNotification::class)
            ->count();
        $this->assertSame(1, $firstRunCount);

        $this->artisan('homeworks:notify-due-soon');
        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true]);

        $secondRunCount = \Illuminate\Notifications\DatabaseNotification::where('notifiable_id', $user->id)
            ->where('type', HomeworkDueSoonNotification::class)
            ->count();
        $this->assertSame(1, $secondRunCount, 'Повторный запуск не должен создавать вторую запись уведомления.');

        \Illuminate\Notifications\DatabaseNotification::where('notifiable_id', $user->id)->delete();
    }

    /** @test */
    public function it_does_not_notify_a_student_who_already_submitted()
    {
        Notification::fake();
        [$user, $course, $homework] = $this->makeHomeworkDueSoon();

        Submission::create([
            'homework_id' => $homework->id,
            'user_id' => $user->id,
            'status' => 'checked',
            'per_task_results' => [],
            'autocheck_score' => 0,
            'manual_score' => 0,
            'total_score' => 0,
        ]);

        $this->artisan('homeworks:notify-due-soon');

        Notification::assertNotSentTo($user, HomeworkDueSoonNotification::class);
    }
}
