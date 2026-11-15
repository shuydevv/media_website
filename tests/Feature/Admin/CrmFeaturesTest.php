<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\CourseUser;
use App\Models\CrmEvent;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserActivityDay;
use App\Service\BillingService;
use App\Service\EnrollmentService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * CRM: оплата пакетом, причина отказа, история, «Не занимается», прогноз,
 * утренняя сводка.
 */
class CrmFeaturesTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function student(array $attrs = []): User
    {
        return User::factory()->create(['role' => User::ROLE_READER, 'email_verified_at' => now()] + $attrs);
    }

    private function course(string $title): Course
    {
        return Course::create(['title' => $title, 'description' => 'Тест', 'price_cents' => 440000]);
    }

    /** @test */
    public function package_payment_extends_the_second_course_for_zero()
    {
        $u = $this->student();
        $a = $this->course('Общество');
        $b = $this->course('История');
        foreach ([$a, $b] as $c) {
            app(EnrollmentService::class)->enrollUser($u, $c, ['source' => 'manual']);
        }

        $this->actingAs($this->admin)->postJson(route('admin.crm.payments.store', [$u, $a]), [
            'amount_rub' => 6400,
            'paid_at' => now()->toDateString(),
            'billing_interval_days' => 30,
            'also_course_ids' => [$b->id],
        ])->assertOk()->assertJsonStructure(['also' => [$b->id => ['access_until']]]);

        $this->assertSame(640000, (int) Payment::where('course_id', $a->id)->value('amount_cents'));
        $this->assertSame(0, (int) Payment::where('course_id', $b->id)->value('amount_cents'));
        $this->assertNotNull(CourseUser::where('user_id', $u->id)->where('course_id', $b->id)->value('next_payment_due_at'));
    }

    /** @test */
    public function lost_needs_a_reason_and_shows_in_history_and_archive()
    {
        $u = $this->student(['first_name' => 'Ушедший']);

        $this->actingAs($this->admin)->patchJson(route('admin.crm.checklist.update', $u), [
            'stage' => 'lost', 'lost_reason' => 'expensive', 'lost_comment' => 'вернётся летом',
        ])->assertOk();

        $this->assertSame('expensive', $u->fresh()->crm_lost_reason);
        $this->assertSame('Отказался: Дорого — вернётся летом', CrmEvent::where('user_id', $u->id)->value('text'));

        $this->actingAs($this->admin)->get(route('admin.crm.archive'))
            ->assertOk()
            ->assertSee('Почему отказываются')
            ->assertSee('Причина: Дорого')
            ->assertSee('Отказался: Дорого — вернётся летом');

        // Вернули в работу — причина стирается.
        $this->actingAs($this->admin)->patchJson(route('admin.crm.checklist.update', $u), ['stage' => 'contacted'])->assertOk();
        $this->assertNull($u->fresh()->crm_lost_reason);
    }

    /** @test */
    public function card_history_shows_payments_and_reminded_marks()
    {
        $u = $this->student();
        $c = $this->course('Общество');
        app(BillingService::class)->recordPayment($u, $c, 640000, 'manual', ['billing_interval_days' => 30]);

        $this->actingAs($this->admin)->patchJson(route('admin.crm.reminded.update', [$u, $c]), ['reminded' => true])->assertOk();

        $this->actingAs($this->admin)->get(route('admin.crm.index'))
            ->assertOk()
            ->assertSee('История')
            ->assertSee('Оплата 6 400 ₽ · Общество')
            ->assertSee('Напомнил об оплате · Общество');
    }

    /** @test */
    public function inactive_chip_finds_students_with_access_who_stopped_coming()
    {
        $c = $this->course('Общество');
        $idle = $this->student(['first_name' => 'Пропавший']);
        $busy = $this->student(['first_name' => 'Старательный']);
        foreach ([$idle, $busy] as $u) {
            app(BillingService::class)->recordPayment($u, $c, 440000, 'manual', ['billing_interval_days' => 30]);
        }
        UserActivityDay::create(['user_id' => $idle->id, 'date' => now()->subDays(12)->toDateString(), 'active_slots' => 1]);
        UserActivityDay::create(['user_id' => $busy->id, 'date' => now()->toDateString(), 'active_slots' => 1]);

        $this->actingAs($this->admin)->get(route('admin.crm.index', ['inactive' => 1]))
            ->assertOk()
            ->assertViewHas('inactiveCount', 1)
            ->assertSee('Заходил 12 дн. назад')
            ->assertDontSee('data-user-id="'.$busy->id.'"', false);
    }

    /** @test */
    public function forecast_counts_this_month_dues_by_last_payment()
    {
        Carbon::setTestNow('2026-11-10 12:00:00');
        $c = $this->course('Общество');
        $u = $this->student();
        app(BillingService::class)->recordPayment($u, $c, 640000, 'manual', [
            'billing_interval_days' => 30,
            'paid_at' => Carbon::parse('2026-10-20 12:00:00'),
        ]); // до 20 ноя — в этом месяце

        $later = $this->student();
        app(BillingService::class)->recordPayment($later, $c, 440000, 'manual', [
            'billing_interval_days' => 30,
            'paid_at' => Carbon::parse('2026-11-05 12:00:00'),
        ]); // до 5 дек — не в этом месяце

        $this->actingAs($this->admin)->get(route('admin.crm.index'))
            ->assertOk()
            ->assertViewHas('forecast', fn ($f) => $f['rub'] === 6400 && $f['students'] === 1);

        Carbon::setTestNow();
    }

    /** @test */
    public function morning_digest_lists_reminders_and_overdue()
    {
        $c = $this->course('Общество');
        $u = $this->student(['first_name' => 'Должник']);
        app(BillingService::class)->recordPayment($u, $c, 440000, 'manual', [
            'billing_interval_days' => 30,
            'paid_at' => now()->subDays(35),
        ]);

        $this->artisan('crm:morning-digest', ['--dry' => true])
            ->expectsOutputToContain('Просрочена оплата: 1')
            ->expectsOutputToContain('Должник')
            ->assertSuccessful();
    }
}
