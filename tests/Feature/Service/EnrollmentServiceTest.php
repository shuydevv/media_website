<?php

namespace Tests\Feature\Service;

use App\Models\Course;
use App\Models\CourseUser;
use App\Models\User;
use App\Notifications\EnrolledInCourseNotification;
use App\Service\EnrollmentService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Как и в BillingServiceTest — нет отдельной тестовой БД, задействованные
 * таблицы MyISAM (транзакции не откатываются), поэтому здесь ручная очистка
 * в tearDown() вместо DatabaseTransactions/RefreshDatabase.
 */
class EnrollmentServiceTest extends TestCase
{
    private EnrollmentService $enroll;

    /** @var int[] */
    private array $createdUserIds = [];

    /** @var int[] */
    private array $createdCourseIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        // См. комментарий в BillingServiceTest::setUp() — enrollUser() шлёт
        // реальные уведомления, фейкуем на уровне класса, чтобы не оставлять
        // необработанные jobs, которые потом "всплывают" в другом тесте.
        Notification::fake();
        $this->enroll = app(EnrollmentService::class);
    }

    protected function tearDown(): void
    {
        if ($this->createdCourseIds !== []) {
            CourseUser::whereIn('course_id', $this->createdCourseIds)->delete();
            Course::whereIn('id', $this->createdCourseIds)->forceDelete();
        }
        if ($this->createdUserIds !== []) {
            User::whereIn('id', $this->createdUserIds)->delete();
        }

        parent::tearDown();
    }

    private function makeUser(): User
    {
        $user = User::factory()->create(['role' => User::ROLE_READER]);
        $this->createdUserIds[] = $user->id;
        return $user;
    }

    private function makeCourse(): Course
    {
        $course = Course::create([
            'title' => 'ТЕСТ (авто-тест EnrollmentServiceTest, безопасно удалять)',
            'description' => 'Тест',
            'price_cents' => 100000,
        ]);
        $this->createdCourseIds[] = $course->id;
        return $course;
    }

    /** @test */
    public function first_enrollment_sends_a_notification()
    {
        Notification::fake();

        $user = $this->makeUser();
        $course = $this->makeCourse();

        $this->enroll->enrollUser($user, $course, ['source' => 'manual']);

        Notification::assertSentTo($user, EnrolledInCourseNotification::class);
    }

    /** @test */
    public function re_enrolling_the_same_user_into_the_same_course_does_not_notify_again()
    {
        Notification::fake();

        $user = $this->makeUser();
        $course = $this->makeCourse();

        $this->enroll->enrollUser($user, $course, ['source' => 'manual']);
        // Второй вызов на ту же пару user+course — например, повторный редим
        // промокода на уже подключённый курс (см. RedeemController::redeem()).
        $this->enroll->enrollUser($user, $course, ['source' => 'promo']);

        Notification::assertSentToTimes($user, EnrolledInCourseNotification::class, 1);
    }
}
