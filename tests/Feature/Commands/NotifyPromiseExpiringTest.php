<?php

namespace Tests\Feature\Commands;

use App\Models\Course;
use App\Models\CourseUser;
use App\Models\User;
use App\Notifications\PromisedPaymentExpiringNotification;
use App\Service\EnrollmentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Нет отдельной тестовой БД, задействованные таблицы MyISAM — ручная очистка, см. BillingServiceTest. */
class NotifyPromiseExpiringTest extends TestCase
{
    /** @var int[] */
    private array $createdUserIds = [];

    /** @var int[] */
    private array $createdCourseIds = [];

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        if ($this->createdCourseIds !== []) {
            CourseUser::whereIn('course_id', $this->createdCourseIds)->delete();
            Course::whereIn('id', $this->createdCourseIds)->forceDelete();
        }
        if ($this->createdUserIds !== []) {
            User::whereIn('id', $this->createdUserIds)->delete();
        }
        parent::tearDown();
    }

    private function makeEnrollmentWithExpiringPromise(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_READER]);
        $this->createdUserIds[] = $user->id;

        $course = Course::create([
            'title' => 'ТЕСТ (авто-тест NotifyPromiseExpiringTest, безопасно удалять)',
            'description' => 'Тест',
            'price_cents' => 100000,
        ]);
        $this->createdCourseIds[] = $course->id;

        app(EnrollmentService::class)->enrollUser($user, $course, ['source' => 'manual']);

        $pivot = CourseUser::where('user_id', $user->id)->where('course_id', $course->id)->firstOrFail();
        $pivot->update(['promised_payment_expires_at' => now()->addHours(6)]);

        return [$user, $course];
    }

    /** @test */
    public function it_notifies_students_whose_promise_expires_within_a_day()
    {
        Notification::fake();
        [$user] = $this->makeEnrollmentWithExpiringPromise();

        $this->artisan('billing:notify-promise-expiring');

        Notification::assertSentTo($user, PromisedPaymentExpiringNotification::class);
    }

    /** @test */
    public function running_it_twice_does_not_notify_again()
    {
        Notification::fake();
        [$user] = $this->makeEnrollmentWithExpiringPromise();

        $this->artisan('billing:notify-promise-expiring');
        $this->artisan('billing:notify-promise-expiring');

        Notification::assertSentToTimes($user, PromisedPaymentExpiringNotification::class, 1);
    }

    /** @test */
    public function it_does_not_notify_when_the_promise_expires_far_in_the_future()
    {
        Notification::fake();
        [$user, $course] = $this->makeEnrollmentWithExpiringPromise();

        $pivot = CourseUser::where('user_id', $user->id)->where('course_id', $course->id)->firstOrFail();
        $pivot->update(['promised_payment_expires_at' => now()->addDays(3)]);

        $this->artisan('billing:notify-promise-expiring');

        Notification::assertNotSentTo($user, PromisedPaymentExpiringNotification::class);
    }
}
