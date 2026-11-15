<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\CourseUser;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentConfirmedNotification;
use App\Service\EnrollmentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Галочки «Напомнил»/«Оплатил» и отмена платежа в карточке /admin/crm.
 */
class CrmPaymentMarksTest extends TestCase
{
    private User $admin;
    private User $student;
    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-15 10:00:00');
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->student = User::factory()->create(['role' => User::ROLE_READER, 'email_verified_at' => now()]);
        $this->course = Course::create(['title' => 'Курс оплаты', 'description' => 'Тест', 'price_cents' => 440000]);

        app(EnrollmentService::class)->enrollUser($this->student, $this->course, ['source' => 'manual']);
        $this->pivot()->update([
            'status' => 'active',
            'billing_interval_days' => 30,
            'expires_at' => null,
            'next_payment_due_at' => Carbon::parse('2026-11-17 23:59:59'),
        ]);
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pivot(): CourseUser
    {
        return CourseUser::where('user_id', $this->student->id)->where('course_id', $this->course->id)->firstOrFail();
    }

    /** @test */
    public function marks_are_shown_when_due_is_near_and_hidden_for_autopay()
    {
        $this->actingAs($this->admin)->get(route('admin.crm.index'))
            ->assertOk()
            ->assertSee('type="checkbox" data-mark-reminded', false)
            ->assertSee('type="checkbox" data-mark-paid', false);

        $this->pivot()->update(['autopay_enabled' => true]);

        $this->actingAs($this->admin)->get(route('admin.crm.index'))
            ->assertOk()
            ->assertDontSee('type="checkbox" data-mark-paid', false);
    }

    /** @test */
    public function reminded_mark_is_stored_without_notifying_the_student()
    {
        $this->actingAs($this->admin)
            ->patchJson(route('admin.crm.reminded.update', [$this->student, $this->course]), ['reminded' => true])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $pivot = $this->pivot();
        $this->assertNotNull($pivot->admin_reminded_at);
        $this->assertSame($this->admin->id, (int) $pivot->admin_reminded_by);
        $this->assertNull($pivot->reminder_sent_at);
        Notification::assertNothingSent();

        $this->actingAs($this->admin)
            ->get(route('admin.crm.index', ['reminded' => 1]))
            ->assertSee('Курс оплаты');
    }

    /** @test */
    public function paid_extends_from_previous_due_date_and_clears_reminded_mark()
    {
        $this->pivot()->update(['admin_reminded_at' => now(), 'admin_reminded_by' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.crm.payments.store', [$this->student, $this->course]), [
                'amount_rub' => 4400,
                'paid_at' => '2026-11-15',
                'billing_interval_days' => 30,
            ])
            ->assertOk();

        $pivot = $this->pivot();
        $this->assertSame('2026-12-17', $pivot->next_payment_due_at->toDateString());
        $this->assertNull($pivot->admin_reminded_at);
        $this->assertSame(440000, Payment::where('course_user_id', $pivot->id)->value('amount_cents'));
        Notification::assertSentTo($this->student, PaymentConfirmedNotification::class);
    }

    /** @test */
    public function double_submit_is_rejected()
    {
        $payload = ['amount_rub' => 4400, 'paid_at' => '2026-11-15', 'billing_interval_days' => 30];
        $url = route('admin.crm.payments.store', [$this->student, $this->course]);

        $this->actingAs($this->admin)->postJson($url, $payload)->assertOk();
        $this->actingAs($this->admin)->postJson($url, $payload)->assertStatus(409);

        $this->assertSame(1, Payment::count());
    }

    /** @test */
    public function future_paid_date_is_rejected()
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.crm.payments.store', [$this->student, $this->course]), [
                'amount_rub' => 4400, 'paid_at' => '2026-11-20', 'billing_interval_days' => 30,
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function undo_restores_previous_state()
    {
        $this->pivot()->update(['admin_reminded_at' => Carbon::parse('2026-11-14 09:00:00'), 'admin_reminded_by' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.crm.payments.store', [$this->student, $this->course]), [
                'amount_rub' => 4400, 'paid_at' => '2026-11-15', 'billing_interval_days' => 14,
            ])
            ->assertOk();

        $payment = Payment::firstOrFail();

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.crm.payments.destroy', $payment))
            ->assertOk();

        $pivot = $this->pivot();
        $this->assertSame(0, Payment::count());
        $this->assertSame('2026-11-17', $pivot->next_payment_due_at->toDateString());
        $this->assertSame(30, (int) $pivot->billing_interval_days);
        $this->assertSame('2026-11-14', $pivot->admin_reminded_at->toDateString());
    }

    /** @test */
    public function only_the_latest_payment_can_be_undone()
    {
        $url = route('admin.crm.payments.store', [$this->student, $this->course]);
        $this->actingAs($this->admin)->postJson($url, ['amount_rub' => 4400, 'paid_at' => '2026-11-15', 'billing_interval_days' => 30])->assertOk();
        $first = Payment::firstOrFail();
        $this->actingAs($this->admin)->postJson($url, ['amount_rub' => 3000, 'paid_at' => '2026-11-15', 'billing_interval_days' => 30])->assertOk();

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.crm.payments.destroy', $first))
            ->assertStatus(422);
    }
}
