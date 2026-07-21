<?php

namespace Tests\Feature\Commands;

use App\Models\Course;
use App\Models\CourseSession;
use App\Models\CourseUser;
use App\Models\Lesson;
use App\Models\User;
use App\Notifications\LessonStartingSoonNotification;
use App\Service\EnrollmentService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Нет отдельной тестовой БД, задействованные таблицы MyISAM — ручная очистка, см. BillingServiceTest. */
class NotifyLessonStartingSoonTest extends TestCase
{
    /** @var int[] */
    private array $createdUserIds = [];

    /** @var int[] */
    private array $createdCourseIds = [];

    /** @var int[] */
    private array $createdSessionIds = [];

    protected function tearDown(): void
    {
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

    private function makeEnrollmentWithSessionStartingSoon(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_READER]);
        $this->createdUserIds[] = $user->id;

        $course = Course::create([
            'title' => 'ТЕСТ (авто-тест NotifyLessonStartingSoonTest, безопасно удалять)',
            'description' => 'Тест',
            'price_cents' => 100000,
        ]);
        $this->createdCourseIds[] = $course->id;

        app(EnrollmentService::class)->enrollUser($user, $course, ['source' => 'manual']);

        $session = CourseSession::create([
            'course_id' => $course->id,
            'date' => now()->toDateString(),
            'start_time' => now()->addMinutes(10)->format('H:i:s'),
            'end_time' => now()->addMinutes(70)->format('H:i:s'),
            'duration_minutes' => 60,
            'status' => 'active',
        ]);
        $this->createdSessionIds[] = $session->id;

        return [$user, $course, $session];
    }

    /** @test */
    public function it_notifies_students_of_a_session_starting_within_30_minutes()
    {
        Notification::fake();
        [$user] = $this->makeEnrollmentWithSessionStartingSoon();

        $this->artisan('lessons:notify-starting-soon');

        Notification::assertSentTo($user, LessonStartingSoonNotification::class);
    }

    /** @test */
    public function running_it_twice_does_not_notify_the_same_session_again()
    {
        Notification::fake();
        [$user] = $this->makeEnrollmentWithSessionStartingSoon();

        $this->artisan('lessons:notify-starting-soon');
        $this->artisan('lessons:notify-starting-soon');

        Notification::assertSentToTimes($user, LessonStartingSoonNotification::class, 1);
    }

    /** @test */
    public function it_does_not_notify_for_a_session_starting_far_in_the_future()
    {
        Notification::fake();
        [$user, $course, $session] = $this->makeEnrollmentWithSessionStartingSoon();

        $session->update(['start_time' => now()->addHours(3)->format('H:i:s')]);

        $this->artisan('lessons:notify-starting-soon');

        Notification::assertNotSentTo($user, LessonStartingSoonNotification::class);
    }
}
