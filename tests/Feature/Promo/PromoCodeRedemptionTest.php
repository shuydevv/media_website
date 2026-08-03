<?php

namespace Tests\Feature\Promo;

use App\Models\Course;
use App\Models\CourseUser;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use App\Models\User;
use App\Service\BillingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression-тесты на баги промокодов из аудита 2026-08-03:
 * - повторное применение access-кода тем же пользователем (RedeemController);
 * - лимит max_uses;
 * - переприменение discount-кода без отключения текущего (BillingService);
 * - что promo_codes/course_user реально на InnoDB и lockForUpdate() даёт
 *   настоящую row-level блокировку (см. миграцию
 *   2026_08_03_190000_convert_promo_tables_to_innodb) — раньше вся БД была
 *   на MyISAM, где DB::transaction()/lockForUpdate() не давали никакой
 *   реальной защиты от гонки, несмотря на то что код выглядел защищённым.
 *
 * Как и в BillingServiceTest/EnrollmentServiceTest — нет отдельной тестовой
 * БД, часть таблиц (users/courses) всё ещё MyISAM, поэтому ручная очистка в
 * tearDown() вместо DatabaseTransactions/RefreshDatabase.
 */
class PromoCodeRedemptionTest extends TestCase
{
    /** @var int[] */
    private array $createdUserIds = [];

    /** @var int[] */
    private array $createdCourseIds = [];

    /** @var int[] */
    private array $createdPromoIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    protected function tearDown(): void
    {
        if ($this->createdCourseIds !== []) {
            PromoRedemption::whereIn('course_id', $this->createdCourseIds)->delete();
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
            'title' => 'ТЕСТ (авто-тест PromoCodeRedemptionTest, безопасно удалять)',
            'description' => 'Тест',
            'price_cents' => 100000,
        ]);
        $this->createdCourseIds[] = $course->id;
        return $course;
    }

    private function makeAccessPromo(array $overrides = []): PromoCode
    {
        $promo = PromoCode::create(array_merge([
            'code' => 'ACCESS-' . Str::random(6),
            'duration_days' => 30,
            'is_active' => true,
            'kind' => 'access',
        ], $overrides));
        $this->createdPromoIds[] = $promo->id;
        return $promo;
    }

    private function makeDiscountPromo(array $overrides = []): PromoCode
    {
        $promo = PromoCode::create(array_merge([
            'code' => 'DISC-' . Str::random(6),
            'duration_days' => 0,
            'is_active' => true,
            'kind' => 'discount',
            'discount_mode' => 'percent',
            'discount_percent' => 20,
        ], $overrides));
        $this->createdPromoIds[] = $promo->id;
        return $promo;
    }

    /** @test */
    public function redeeming_the_same_access_code_twice_by_the_same_user_is_rejected()
    {
        $user = $this->makeUser();
        $course = $this->makeCourse();
        $promo = $this->makeAccessPromo(['course_id' => $course->id]);

        $this->actingAs($user)
            ->post(route('promo.redeem'), ['code' => $promo->code])
            ->assertRedirect(route('student.dashboard'));

        $this->assertSame(1, $promo->fresh()->used_count);
        $this->assertSame(
            1,
            PromoRedemption::where('promo_code_id', $promo->id)->where('user_id', $user->id)->count()
        );

        // Повторная активация того же кода тем же пользователем — раньше
        // ничего не мешало продлить доступ ещё раз и потратить лимит,
        // рассчитанный на разных студентов.
        $second = $this->actingAs($user)->post(route('promo.redeem'), ['code' => $promo->code]);
        $second->assertSessionHasErrors('code');

        $this->assertSame(1, $promo->fresh()->used_count);
        $this->assertSame(
            1,
            PromoRedemption::where('promo_code_id', $promo->id)->where('user_id', $user->id)->count()
        );
    }

    /** @test */
    public function redemption_is_rejected_once_max_uses_is_reached()
    {
        $firstUser = $this->makeUser();
        $secondUser = $this->makeUser();
        $course = $this->makeCourse();
        $promo = $this->makeAccessPromo(['course_id' => $course->id, 'max_uses' => 1]);

        $this->actingAs($firstUser)
            ->post(route('promo.redeem'), ['code' => $promo->code])
            ->assertRedirect(route('student.dashboard'));

        $response = $this->actingAs($secondUser)->post(route('promo.redeem'), ['code' => $promo->code]);
        $response->assertSessionHasErrors('code');

        $this->assertSame(1, $promo->fresh()->used_count);
        $this->assertFalse($this->app->make(BillingService::class)->hasAccess($secondUser, $course));
    }

    /** @test */
    public function applying_a_discount_code_while_one_is_already_attached_is_rejected()
    {
        $billing = $this->app->make(BillingService::class);

        $user = $this->makeUser();
        $course = $this->makeCourse();
        $billing->recordPayment($user, $course, 100000, 'manual', ['billing_interval_days' => 30]);

        $first = $this->makeDiscountPromo(['course_id' => $course->id]);
        $second = $this->makeDiscountPromo(['course_id' => $course->id]);

        $billing->applyPromoCode($user, $course, $first->code);
        $this->assertSame(1, $first->fresh()->used_count);

        // Раньше applyPromoCode() ничего не проверял, кроме лимита самого
        // кода — можно было переподключать любой код поверх уже
        // применённого сколько угодно раз через прямой POST, каждый раз
        // тратя used_count без per-user лимита (в UI форма просто скрыта).
        $this->expectException(\DomainException::class);
        try {
            $billing->applyPromoCode($user, $course, $second->code);
        } finally {
            $this->assertSame(0, $second->fresh()->used_count);
            $this->assertSame($first->id, CourseUser::where('user_id', $user->id)
                ->where('course_id', $course->id)->first()->promo_code_id);
        }
    }

    /** @test */
    public function removing_and_reapplying_a_discount_code_is_allowed()
    {
        $billing = $this->app->make(BillingService::class);

        $user = $this->makeUser();
        $course = $this->makeCourse();
        $billing->recordPayment($user, $course, 100000, 'manual', ['billing_interval_days' => 30]);

        $promo = $this->makeDiscountPromo(['course_id' => $course->id]);

        $billing->applyPromoCode($user, $course, $promo->code);
        $billing->removePromoCode($user, $course);

        // После явного отключения подключить (в том числе тот же) код снова
        // можно — блокируется только "подключение поверх подключённого".
        $billing->applyPromoCode($user, $course, $promo->code);

        $this->assertSame($promo->id, CourseUser::where('user_id', $user->id)
            ->where('course_id', $course->id)->first()->promo_code_id);
    }

    /** @test */
    public function the_promo_code_row_lock_actually_blocks_a_concurrent_connection()
    {
        $promo = $this->makeAccessPromo();

        config(['database.connections.promo_lock_probe' => config('database.connections.mysql')]);
        DB::purge('promo_lock_probe');
        $second = DB::connection('promo_lock_probe');
        $second->statement('SET SESSION innodb_lock_wait_timeout = 1');

        DB::beginTransaction();
        $blocked = false;
        try {
            PromoCode::whereKey($promo->id)->lockForUpdate()->first();

            try {
                $second->transaction(function () use ($second, $promo) {
                    $second->table('promo_codes')->where('id', $promo->id)->lockForUpdate()->get();
                });
            } catch (\Illuminate\Database\QueryException $e) {
                $blocked = str_contains($e->getMessage(), 'Lock wait timeout exceeded');
            }
        } finally {
            DB::rollBack();
            DB::purge('promo_lock_probe');
        }

        // Если это не InnoDB (например, таблицу снова перевели на MyISAM),
        // lockForUpdate() ничего не блокирует, второе соединение спокойно
        // читает "запертую" строку, $blocked остаётся false — именно так
        // раньше молча ломалась защита от гонки за max_uses.
        $this->assertTrue(
            $blocked,
            'Ожидали Lock wait timeout — promo_codes должна быть на InnoDB с реальным row-level locking.'
        );
    }
}
