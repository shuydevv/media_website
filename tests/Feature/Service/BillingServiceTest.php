<?php

namespace Tests\Feature\Service;

use App\Models\Course;
use App\Models\CourseUser;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\User;
use App\Notifications\PaymentConfirmedNotification;
use App\Service\BillingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * В проекте нет отдельной тестовой БД (phpunit.xml не переопределяет
 * DB_CONNECTION — тесты идут на той же MySQL, что и dev/demo-окружение), и
 * все задействованные здесь таблицы (courses, users, course_user,
 * promo_codes) в этой БД — MyISAM, который транзакции вообще не
 * поддерживает. DatabaseTransactions/RefreshDatabase в такой ситуации
 * молча ничего не откатывают: тестовые записи буквально остаются в
 * реальной dev-базе (так уже случилось один раз при первом прогоне этого
 * файла — пришлось вручную вычищать 16 "Тестовый курс" и связанные
 * user/payment строки). Поэтому здесь ручная очистка: каждый созданный
 * User/Course запоминается и полностью удаляется в tearDown(), в правильном
 * порядке (сначала зависимые payments/course_user, потом сам курс/юзер).
 */
class BillingServiceTest extends TestCase
{
    private BillingService $billing;

    /** @var int[] */
    private array $createdUserIds = [];

    /** @var int[] */
    private array $createdCourseIds = [];

    /** @var int[] */
    private array $createdPromoIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        // recordPayment()/enrollUser() теперь шлют реальные уведомления
        // (ShouldQueue). Без фейка это молча кладёт задания в jobs и
        // ничем не чистится — они потом неожиданно обрабатываются каким-то
        // ДРУГИМ тестом, вызвавшим queue:work, и пишут notifications на уже
        // удалённых тестовых юзеров. Фейкуем на уровне всего класса — эти
        // тесты про биллинг-математику, а не про доставку уведомлений
        // (для этого есть отдельные assertSentTo-тесты ниже).
        Notification::fake();
        $this->billing = app(BillingService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        if ($this->createdCourseIds !== []) {
            Payment::whereIn('course_id', $this->createdCourseIds)->delete();
            CourseUser::whereIn('course_id', $this->createdCourseIds)->delete();
            Course::whereIn('id', $this->createdCourseIds)->forceDelete();
        }
        if ($this->createdPromoIds !== []) {
            PromoCode::whereIn('id', $this->createdPromoIds)->delete();
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
            'title' => 'ТЕСТ (авто-тест BillingServiceTest, безопасно удалять)',
            'description' => 'Тест',
            'price_cents' => 100000,
        ]);
        $this->createdCourseIds[] = $course->id;
        return $course;
    }

    private function pivot(User $user, Course $course): CourseUser
    {
        return CourseUser::where('user_id', $user->id)->where('course_id', $course->id)->firstOrFail();
    }

    /** @test */
    public function first_payment_anchors_the_due_date_to_the_payment_date()
    {
        $user = $this->makeUser();
        $course = $this->makeCourse();

        $paidAt = Carbon::parse('2026-01-10 12:00:00');
        $this->billing->recordPayment($user, $course, 100000, 'manual', [
            'billing_interval_days' => 30,
            'paid_at' => $paidAt,
        ]);

        $pivot = $this->pivot($user, $course);
        $this->assertTrue($pivot->next_payment_due_at->equalTo($paidAt->copy()->addMonthNoOverflow()));
    }

    /** @test */
    public function on_time_renewal_keeps_the_original_schedule_instead_of_shifting_to_the_payment_date()
    {
        $user = $this->makeUser();
        $course = $this->makeCourse();

        $this->billing->recordPayment($user, $course, 100000, 'manual', [
            'billing_interval_days' => 30,
            'paid_at' => Carbon::parse('2026-01-05 12:00:00'),
        ]);
        $firstDue = $this->pivot($user, $course)->next_payment_due_at; // 2026-02-05

        // Второй платёж приходит на 2 дня раньше срока — расписание не должно
        // "поехать" на дату фактической оплаты, урок за период всё равно шёл
        // по старому графику.
        $secondPaidAt = $firstDue->copy()->subDays(2);
        $this->billing->recordPayment($user, $course, 100000, 'manual', [
            'billing_interval_days' => 30,
            'paid_at' => $secondPaidAt,
        ]);

        $secondDue = $this->pivot($user, $course)->next_payment_due_at;
        $this->assertTrue($secondDue->equalTo($firstDue->copy()->addMonthNoOverflow()));
        $this->assertFalse($secondDue->equalTo($secondPaidAt->copy()->addMonthNoOverflow()));
    }

    /** @test */
    public function a_late_payment_within_the_catch_up_window_still_preserves_the_original_schedule()
    {
        $user = $this->makeUser();
        $course = $this->makeCourse();

        $this->billing->recordPayment($user, $course, 100000, 'manual', [
            'billing_interval_days' => 30,
            'paid_at' => Carbon::parse('2026-01-05 12:00:00'),
        ]);
        $firstDue = $this->pivot($user, $course)->next_payment_due_at; // 2026-02-05

        // Опоздание на 10 дней — меньше MAX_CATCH_UP_DAYS (30), расписание
        // остаётся привязано к исходной дате, а не к дате фактической оплаты.
        $latePaidAt = $firstDue->copy()->addDays(10);
        $this->billing->recordPayment($user, $course, 100000, 'manual', [
            'billing_interval_days' => 30,
            'paid_at' => $latePaidAt,
        ]);

        $secondDue = $this->pivot($user, $course)->next_payment_due_at;
        $this->assertTrue($secondDue->equalTo($firstDue->copy()->addMonthNoOverflow()));
    }

    /** @test */
    public function a_payment_beyond_the_catch_up_window_resets_the_schedule_to_the_payment_date()
    {
        $user = $this->makeUser();
        $course = $this->makeCourse();

        $this->billing->recordPayment($user, $course, 100000, 'manual', [
            'billing_interval_days' => 30,
            'paid_at' => Carbon::parse('2026-01-05 12:00:00'),
        ]);
        $firstDue = $this->pivot($user, $course)->next_payment_due_at; // 2026-02-05

        // Опоздание на 31 день — больше MAX_CATCH_UP_DAYS (30), это уже
        // "возврат после долгого перерыва": график пересчитывается от
        // фактической даты оплаты, а не тянется от старого долга.
        $latePaidAt = $firstDue->copy()->addDays(self::catchUpThreshold() + 1);
        $this->billing->recordPayment($user, $course, 100000, 'manual', [
            'billing_interval_days' => 30,
            'paid_at' => $latePaidAt,
        ]);

        $secondDue = $this->pivot($user, $course)->next_payment_due_at;
        $this->assertTrue($secondDue->equalTo($latePaidAt->copy()->addMonthNoOverflow()));
    }

    /** @test */
    public function monthly_period_is_a_calendar_month_and_does_not_drift()
    {
        $user = $this->makeUser();
        $course = $this->makeCourse();

        foreach (['2026-10-16' => '2026-11-16', '2026-11-15' => '2026-12-16'] as $paid => $expected) {
            $this->billing->recordPayment($user, $course, 100000, 'manual', [
                'billing_interval_days' => 30,
                'paid_at' => Carbon::parse($paid.' 12:00:00'),
            ]);
            $this->assertSame($expected, $this->pivot($user, $course)->next_payment_due_at->toDateString());
        }
    }

    /** @test */
    public function monthly_period_from_the_31st_ends_on_the_last_day_of_a_short_month()
    {
        $user = $this->makeUser();
        $course = $this->makeCourse();

        $this->billing->recordPayment($user, $course, 100000, 'manual', [
            'billing_interval_days' => 30,
            'paid_at' => Carbon::parse('2027-01-31 12:00:00'),
        ]);

        $this->assertSame('2027-02-28', $this->pivot($user, $course)->next_payment_due_at->toDateString());
    }

    /** @test */
    public function monthly_date_returns_to_the_31st_after_february()
    {
        $user = $this->makeUser();
        $course = $this->makeCourse();

        foreach (['2027-01-31' => '2027-02-28', '2027-02-27' => '2027-03-31'] as $paid => $expected) {
            $this->billing->recordPayment($user, $course, 100000, 'manual', [
                'billing_interval_days' => 30,
                'paid_at' => Carbon::parse($paid.' 12:00:00'),
            ]);
            $this->assertSame($expected, $this->pivot($user, $course)->next_payment_due_at->toDateString());
        }
    }

    /** @test */
    public function a_drifted_date_snaps_back_to_the_first_payment_day_but_a_gifted_week_is_kept()
    {
        $user = $this->makeUser();
        $course = $this->makeCourse();

        $this->billing->recordPayment($user, $course, 100000, 'manual', [
            'billing_interval_days' => 30,
            'paid_at' => Carbon::parse('2026-09-16 12:00:00'),
        ]);

        // Старая запись (до billing_anchor_day): дата сползла на день, так
        // считалось «+30 дней» — 17 ноя. Опора — число первой оплаты (16).
        CourseUser::whereKey($this->pivot($user, $course)->id)->update(['next_payment_due_at' => '2026-11-17 12:00:00', 'billing_anchor_day' => null]);
        $this->billing->recordPayment($user, $course, 100000, 'manual', [
            'billing_interval_days' => 30,
            'paid_at' => Carbon::parse('2026-11-15 12:00:00'),
        ]);
        $this->assertSame('2026-12-16', $this->pivot($user, $course)->next_payment_due_at->toDateString());

        // Подарили неделю: 23 дек — к 16-му не притягиваем.
        CourseUser::whereKey($this->pivot($user, $course)->id)->update(['next_payment_due_at' => '2026-12-23 12:00:00']);
        $this->billing->recordPayment($user, $course, 100000, 'manual', [
            'billing_interval_days' => 30,
            'paid_at' => Carbon::parse('2026-12-20 12:00:00'),
        ]);
        $this->assertSame('2027-01-23', $this->pivot($user, $course)->next_payment_due_at->toDateString());
    }

    /** @test */
    public function a_manual_crm_date_becomes_the_new_monthly_day()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = $this->makeUser();
        $course = $this->makeCourse();

        $this->billing->recordPayment($user, $course, 100000, 'manual', [
            'billing_interval_days' => 30,
            'paid_at' => Carbon::parse('2026-10-16 12:00:00'),
        ]);

        $this->actingAs($admin)->patchJson(route('admin.crm.access.update', [$user, $course]), [
            'access_until' => '2026-11-18',
        ])->assertOk();

        $this->billing->recordPayment($user, $course, 100000, 'manual', [
            'billing_interval_days' => 30,
            'paid_at' => Carbon::parse('2026-11-17 12:00:00'),
        ]);

        $this->assertSame('2026-12-18', $this->pivot($user, $course)->next_payment_due_at->toDateString());
    }

    /** @test */
    public function two_week_period_stays_exactly_fourteen_days()
    {
        $user = $this->makeUser();
        $course = $this->makeCourse();

        $this->billing->recordPayment($user, $course, 100000, 'manual', [
            'billing_interval_days' => 14,
            'paid_at' => Carbon::parse('2026-11-15 12:00:00'),
        ]);

        $this->assertSame('2026-11-29', $this->pivot($user, $course)->next_payment_due_at->toDateString());
    }

    private static function catchUpThreshold(): int
    {
        return BillingService::MAX_CATCH_UP_DAYS;
    }

    /** @test */
    public function access_is_granted_before_the_due_date_and_revoked_after_without_a_promise()
    {
        $user = $this->makeUser();
        $course = $this->makeCourse();

        $this->billing->recordPayment($user, $course, 100000, 'manual', [
            'billing_interval_days' => 30,
            'paid_at' => Carbon::parse('2026-01-05 12:00:00'),
        ]);

        Carbon::setTestNow('2026-01-20 12:00:00');
        $this->assertTrue($this->billing->hasAccess($user, $course));
        $this->assertFalse($this->billing->isPastDue($user, $course));

        Carbon::setTestNow('2026-02-10 12:00:00'); // после 2026-02-04
        $this->assertFalse($this->billing->hasAccess($user, $course));
        $this->assertTrue($this->billing->isPastDue($user, $course));
    }

    /** @test */
    public function a_promised_payment_restores_access_past_the_due_date_until_it_expires()
    {
        $user = $this->makeUser();
        $course = $this->makeCourse();

        $this->billing->recordPayment($user, $course, 100000, 'manual', [
            'billing_interval_days' => 30,
            'paid_at' => Carbon::parse('2026-01-05 12:00:00'),
        ]);

        Carbon::setTestNow('2026-02-10 12:00:00'); // просрочка
        $this->assertFalse($this->billing->hasAccess($user, $course));

        $this->billing->grantPromisedPayment($user, $course);
        $this->assertTrue($this->billing->hasAccess($user, $course));

        Carbon::setTestNow(
            Carbon::parse('2026-02-10 12:00:00')->addDays(BillingService::PROMISE_DAYS + 1)
        );
        $this->assertFalse($this->billing->hasAccess($user, $course));
    }

    /** @test */
    public function record_payment_sends_a_payment_confirmed_notification()
    {
        Notification::fake();

        $user = $this->makeUser();
        $course = $this->makeCourse();

        $this->billing->recordPayment($user, $course, 100000, 'manual', ['billing_interval_days' => 30]);

        Notification::assertSentTo($user, PaymentConfirmedNotification::class);
    }

    /** @test */
    public function granting_a_promised_payment_does_not_send_a_payment_confirmed_notification()
    {
        Notification::fake();

        $user = $this->makeUser();
        $course = $this->makeCourse();

        // Обычный платёж должен быть — иначе isPromiseAvailable() не даст обещание.
        $this->billing->recordPayment($user, $course, 100000, 'manual', [
            'billing_interval_days' => 30,
            'paid_at' => Carbon::parse('2026-01-05 12:00:00'),
        ]);
        Carbon::setTestNow('2026-02-10 12:00:00'); // просрочка

        Notification::fake(); // сбрасываем — интересует только вызов ниже

        $this->billing->grantPromisedPayment($user, $course);

        Notification::assertNothingSent();
    }

    /** @test */
    public function price_for_enrollment_is_the_base_price_without_a_promo_code()
    {
        $user = $this->makeUser();
        $course = $this->makeCourse(); // price_cents = 100000

        $this->billing->recordPayment($user, $course, 100000, 'manual', ['billing_interval_days' => 30]);

        $this->assertSame(100000, $this->billing->priceForEnrollment($user, $course));
    }

    /** @test */
    public function price_for_enrollment_applies_an_attached_percent_discount_promo_code()
    {
        $user = $this->makeUser();
        $course = $this->makeCourse(); // price_cents = 100000

        $this->billing->recordPayment($user, $course, 100000, 'manual', ['billing_interval_days' => 30]);

        $promo = PromoCode::create([
            'code' => 'TEST20',
            'course_id' => $course->id,
            'kind' => 'discount',
            'discount_mode' => 'percent',
            'discount_percent' => 20,
            'is_active' => true,
            'duration_days' => 0, // не используется для kind=discount, но колонка NOT NULL
        ]);
        $this->createdPromoIds[] = $promo->id;

        $this->billing->applyPromoCode($user, $course, 'TEST20');

        // 20% от 100000 = 80000, и скидка продолжает действовать на
        // следующих циклах, а не только в момент подключения.
        $this->assertSame(80000, $this->billing->priceForEnrollment($user, $course));
        $this->assertSame(80000, $this->billing->priceForEnrollment($user, $course));
        $this->assertSame(1, $promo->fresh()->used_count);
    }
}
