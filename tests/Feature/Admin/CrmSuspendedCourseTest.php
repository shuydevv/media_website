<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\CourseUser;
use App\Models\User;
use App\Service\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Приостановленный по просрочке курс (enrollments:expire → suspended) не
 * должен исчезать из карточки ученика в /admin/crm — админ продлевает его
 * сменой даты, а не записывает ученика заново.
 */
class CrmSuspendedCourseTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function suspended_course_is_shown_as_overdue_and_can_be_reactivated_by_date()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $student = User::factory()->create(['role' => User::ROLE_READER, 'email_verified_at' => now()]);
        $course = Course::create(['title' => 'Курс CRM-теста', 'description' => 'Тест', 'price_cents' => 100000]);

        app(EnrollmentService::class)->enrollUser($student, $course, ['source' => 'manual']);
        CourseUser::where('user_id', $student->id)->where('course_id', $course->id)
            ->update(['expires_at' => now()->subDay()]);

        $this->artisan('enrollments:expire');
        $this->assertSame('suspended', CourseUser::where('user_id', $student->id)->value('status'));

        $this->actingAs($admin)
            ->get(route('admin.crm.index'))
            ->assertOk()
            ->assertSee('Курс CRM-теста')
            ->assertSee('data-course-suspended', false)
            ->assertDontSee('Курсов нет');

        $this->actingAs($admin)
            ->patchJson(route('admin.crm.access.update', [$student, $course]), [
                'access_until' => now()->addMonth()->toDateString(),
            ])
            ->assertOk();

        $this->assertSame('active', CourseUser::where('user_id', $student->id)->value('status'));
    }

    /** @test */
    public function crm_manual_payment_becomes_past_due_instead_of_frozen_after_period_ends()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $student = User::factory()->create(['role' => User::ROLE_READER, 'email_verified_at' => now()]);
        $course = Course::create(['title' => 'Курс CRM-оплаты', 'description' => 'Тест', 'price_cents' => 440000]);

        app(EnrollmentService::class)->enrollUser($student, $course, ['source' => 'manual']);

        $this->actingAs($admin)
            ->patchJson(route('admin.crm.access.update', [$student, $course]), [
                'access_until' => now()->addDays(10)->toDateString(),
                'amount_rub' => 4400,
            ])
            ->assertOk();

        $pivot = CourseUser::where('user_id', $student->id)->where('course_id', $course->id)->first();
        $this->assertSame(30, (int) $pivot->billing_interval_days);
        $this->assertNull($pivot->expires_at);

        $this->travel(11)->days();
        $this->artisan('enrollments:expire');

        $pivot->refresh();
        $this->assertSame('active', $pivot->status);
        $this->assertSame('past_due', $student->fresh()->crmStatus()['key']);
        $this->assertTrue(app(\App\Service\BillingService::class)->isPastDue($student, $course));

        $this->actingAs($admin)
            ->get(route('admin.crm.index'))
            ->assertOk()
            ->assertSee('Курс CRM-оплаты');
    }
}
