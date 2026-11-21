<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\CourseUser;
use App\Models\User;
use App\Service\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Курс, с которого админ сам снял ученика (кнопка в CRM), не должен
 * числиться в «Просрочена оплата» утренней сводки; второй, активный курс
 * ученика при этом не трогается и ученик остаётся активным.
 */
class CrmRemoveCourseTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function removed_suspended_course_leaves_digest_but_other_course_stays_active()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $student = User::factory()->create(['role' => User::ROLE_READER, 'email_verified_at' => now()]);
        $old = Course::create(['title' => 'Старый курс', 'description' => 'Тест', 'price_cents' => 100000]);
        $new = Course::create(['title' => 'Новый курс', 'description' => 'Тест', 'price_cents' => 100000]);

        $enroll = app(EnrollmentService::class);
        $enroll->enrollUser($student, $old, ['source' => 'manual', 'expires_at' => now()->subDays(2)]);
        $enroll->enrollUser($student, $new, ['source' => 'manual', 'expires_at' => now()->addMonth()]);
        $this->artisan('enrollments:expire');

        $this->artisan('crm:morning-digest', ['--dry' => true])
            ->expectsOutputToContain('Старый курс');

        $this->actingAs($admin)
            ->patchJson(route('admin.crm.course.remove', [$student, $old]))
            ->assertOk()
            ->assertJsonPath('status.key', 'active');

        $this->assertSame('completed', CourseUser::where('user_id', $student->id)->where('course_id', $old->id)->value('status'));
        $this->assertSame('active', CourseUser::where('user_id', $student->id)->where('course_id', $new->id)->value('status'));

        $this->artisan('crm:morning-digest', ['--dry' => true])
            ->doesntExpectOutputToContain('Старый курс');
    }
}
