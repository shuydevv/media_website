<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\CourseUser;
use App\Models\User;
use App\Service\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «Не учитывать» (crm_excluded): фейк сохраняет доступ, но уходит из
 * «В работе» на вкладку «Не учитываются». И чипы-фильтры CRM — по одному.
 */
class CrmExcludedTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function excluded_student_keeps_access_and_moves_to_its_own_tab()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $fake = User::factory()->create(['role' => User::ROLE_READER, 'email_verified_at' => now(), 'first_name' => 'Фейкович']);
        $course = Course::create(['title' => 'Курс', 'description' => 'Тест', 'price_cents' => 100000]);
        app(EnrollmentService::class)->enrollUser($fake, $course, ['source' => 'manual']);

        $this->actingAs($admin)->get(route('admin.crm.index'))->assertSee('Фейкович');

        $this->actingAs($admin)
            ->patch(route('admin.crm.excluded.update', $fake), ['excluded' => 1])
            ->assertRedirect();

        $this->assertTrue($fake->fresh()->crm_excluded);
        $this->assertSame('active', CourseUser::where('user_id', $fake->id)->value('status'));

        $this->actingAs($admin)->get(route('admin.crm.index'))->assertDontSee('data-user-id="'.$fake->id.'"', false);
        $this->actingAs($admin)->get(route('admin.crm.excluded'))->assertOk()->assertSee('Фейкович')->assertSee('Снова учитывать');
    }

    /** @test */
    public function only_one_quick_filter_applies_at_a_time()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)->get(route('admin.crm.index', ['status' => 'active', 'soon' => 1]));

        $response->assertOk()->assertViewHas('status', 'active')->assertViewHas('soonOnly', false);
    }

    /** @test */
    public function course_stats_count_unique_students_without_fakes()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $real = User::factory()->create(['role' => User::ROLE_READER, 'email_verified_at' => now()]);
        $fake = User::factory()->create(['role' => User::ROLE_READER, 'email_verified_at' => now(), 'crm_excluded' => true]);
        $a = Course::create(['title' => 'Курс А', 'description' => 'Тест', 'price_cents' => 100000]);
        $b = Course::create(['title' => 'Курс Б', 'description' => 'Тест', 'price_cents' => 100000]);
        foreach ([[$real, $a], [$real, $b], [$fake, $a]] as [$u, $c]) {
            app(EnrollmentService::class)->enrollUser($u, $c, ['source' => 'manual']);
        }

        $this->actingAs($admin)->get(route('admin.crm.index'))
            ->assertOk()
            ->assertViewHas('courseStats', fn ($s) => $s['unique'] === 1
                && collect($s['courses'])->pluck('students', 'title')->all() === ['Курс А' => 1, 'Курс Б' => 1])
            ->assertSee('В работе');
    }
}
