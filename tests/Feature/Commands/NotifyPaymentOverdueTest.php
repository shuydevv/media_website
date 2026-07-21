<?php

namespace Tests\Feature\Commands;

use App\Models\Course;
use App\Models\CourseUser;
use App\Models\User;
use App\Notifications\PaymentOverdueNotification;
use App\Service\EnrollmentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Нет отдельной тестовой БД, задействованные таблицы MyISAM — ручная очистка, см. BillingServiceTest. */
class NotifyPaymentOverdueTest extends TestCase
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

    private function makeOverdueEnrollment(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_READER]);
        $this->createdUserIds[] = $user->id;

        $course = Course::create([
            'title' => 'ТЕСТ (авто-тест NotifyPaymentOverdueTest, безопасно удалять)',
            'description' => 'Тест',
            'price_cents' => 100000,
        ]);
        $this->createdCourseIds[] = $course->id;

        app(EnrollmentService::class)->enrollUser($user, $course, ['source' => 'manual']);

        $pivot = CourseUser::where('user_id', $user->id)->where('course_id', $course->id)->firstOrFail();
        $pivot->update([
            'billing_interval_days' => 30,
            'next_payment_due_at' => now()->subDays(2), // просрочено
            'promised_payment_expires_at' => null, // без активного обещания
        ]);

        return [$user, $course];
    }

    /** @test */
    public function it_notifies_students_whose_access_is_actually_suspended()
    {
        Notification::fake();
        [$user, $course] = $this->makeOverdueEnrollment();

        $this->artisan('billing:notify-overdue');

        Notification::assertSentTo($user, PaymentOverdueNotification::class);
    }

    /** @test */
    public function running_it_twice_does_not_notify_the_same_overdue_pivot_again()
    {
        Notification::fake();
        [$user, $course] = $this->makeOverdueEnrollment();

        $this->artisan('billing:notify-overdue');
        $this->artisan('billing:notify-overdue');

        Notification::assertSentToTimes($user, PaymentOverdueNotification::class, 1);
    }

    /** @test */
    public function it_does_not_notify_when_an_active_promise_still_grants_access()
    {
        Notification::fake();
        [$user, $course] = $this->makeOverdueEnrollment();

        $pivot = CourseUser::where('user_id', $user->id)->where('course_id', $course->id)->firstOrFail();
        $pivot->update(['promised_payment_expires_at' => now()->addDays(2)]);

        $this->artisan('billing:notify-overdue');

        // enrollUser() из фикстуры уже шлёт своё EnrolledInCourseNotification —
        // тут важно только что PaymentOverdueNotification не добавилось.
        Notification::assertNotSentTo($user, PaymentOverdueNotification::class);
    }
}
