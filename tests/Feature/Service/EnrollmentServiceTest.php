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

    /** @test */
    public function reactivating_an_existing_enrollment_does_not_reset_enrolled_at()
    {
        Notification::fake();

        $user = $this->makeUser();
        $course = $this->makeCourse();

        $originalEnrolledAt = now()->subMonths(3);

        // Как при первичном редиме промокода (см. RedeemController::redeem()).
        $this->enroll->enrollUser($user, $course, [
            'status' => 'active',
            'enrolled_at' => $originalEnrolledAt,
            'source' => 'promo',
            'promo_code' => 'TEST123',
        ]);

        // Как при ручной реактивации админом после истечения доступа
        // (Admin\User\UpdateController) — enrolled_at/source/promo_code
        // намеренно не передаются, только expires_at.
        $this->enroll->enrollUser($user, $course, [
            'source' => 'manual',
            'expires_at' => now()->addMonth(),
        ]);

        $pivot = CourseUser::where('user_id', $user->id)->where('course_id', $course->id)->firstOrFail();

        $this->assertTrue($pivot->enrolled_at->equalTo($originalEnrolledAt));
        $this->assertSame('promo', $pivot->source);
        $this->assertSame('TEST123', $pivot->promo_code);
    }
}
