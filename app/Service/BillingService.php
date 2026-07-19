<?php

namespace App\Service;

use App\Models\Course;
use App\Models\CourseUser;
use App\Models\Payment;
use App\Models\User;
use App\Service\Pricing\PromoLookup;
use App\Service\Pricing\PromoPricing;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public const PROMISE_DAYS = 5;
    public const ALLOWED_INTERVALS = [14, 30];

    /**
     * Порог "слишком большого разрыва" для recordPayment() — один и тот же
     * для всех периодичностей (не зависит от billing_interval_days), в том
     * числе для двухнедельного плана.
     */
    public const MAX_CATCH_UP_DAYS = 30;

    public function __construct(private EnrollmentService $enroll)
    {
    }

    public function hasAccess(User $user, Course $course): bool
    {
        $pivot = $this->pivot($user, $course);
        if (!$pivot || $pivot->status !== 'active') {
            return false;
        }

        if ($pivot->billing_interval_days === null) {
            // Не на регулярной оплате (промокод/ручной разовый доступ) — старая логика.
            return $pivot->expires_at === null || $pivot->expires_at->gte(now());
        }

        if ($pivot->next_payment_due_at === null) {
            // Защитный случай: recordPayment() всегда пишет оба поля вместе,
            // так что это не должно происходить — не блокируем из-за рассинхрона данных.
            return true;
        }

        if (now()->lt($pivot->next_payment_due_at)) {
            return true;
        }

        // Просрочка — доступ остаётся только если ещё действует обещанный платёж.
        return $pivot->promised_payment_expires_at !== null
            && now()->lt($pivot->promised_payment_expires_at);
    }

    public function isPastDue(User $user, Course $course): bool
    {
        $pivot = $this->pivot($user, $course);

        return $pivot
            && $pivot->status === 'active'
            && $pivot->billing_interval_days !== null
            && $pivot->next_payment_due_at !== null
            && now()->gte($pivot->next_payment_due_at);
    }

    public function isPromiseAvailable(User $user, Course $course): bool
    {
        $pivot = $this->pivot($user, $course);

        return $this->isPastDue($user, $course) && $pivot->promised_payment_used_at === null;
    }

    /**
     * Обещанный платёж сейчас в силе: доступ уже держится не по обычному
     * сроку оплаты, а на честном слове, и ещё не истёк.
     */
    public function isPromiseActive(User $user, Course $course): bool
    {
        $pivot = $this->pivot($user, $course);

        return $pivot !== null
            && $pivot->promised_payment_expires_at !== null
            && now()->lt($pivot->promised_payment_expires_at);
    }

    public function promiseDaysLeft(User $user, Course $course): ?int
    {
        $pivot = $this->pivot($user, $course);
        if (!$pivot || !$this->isPromiseActive($user, $course)) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($pivot->promised_payment_expires_at->copy()->startOfDay(), false);
    }

    public function dueInDays(User $user, Course $course): ?int
    {
        $pivot = $this->pivot($user, $course);
        if (!$pivot || $pivot->billing_interval_days === null || !$pivot->next_payment_due_at) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($pivot->next_payment_due_at->copy()->startOfDay(), false);
    }

    public function nextDueDate(User $user, Course $course): ?Carbon
    {
        return $this->pivot($user, $course)?->next_payment_due_at;
    }

    public function isBillingEnabled(User $user, Course $course): bool
    {
        return $this->pivot($user, $course)?->billing_interval_days !== null;
    }

    public function intervalDays(User $user, Course $course): ?int
    {
        return $this->pivot($user, $course)?->billing_interval_days;
    }

    public function attachedPromoCode(User $user, Course $course): ?\App\Models\PromoCode
    {
        return $this->pivot($user, $course)?->promoCode;
    }

    /**
     * Пока нет реального шлюза, включение автоплатежа ничего физически не
     * списывает — это просто флаг, который переключает уведомления (см.
     * BillingBannerComposer): при автоплатеже баннер "скоро оплата" не нужен,
     * пользователя просто предупреждают письмом. Баннер "доступ приостановлен"
     * при просрочке показывается в любом случае, независимо от автоплатежа.
     */
    public function isAutopayEnabled(User $user, Course $course): bool
    {
        return (bool) $this->pivot($user, $course)?->autopay_enabled;
    }

    public function setAutopayEnabled(User $user, Course $course, bool $enabled): void
    {
        $pivot = $this->pivotOrFail($user, $course);
        $pivot->autopay_enabled = $enabled;
        $pivot->save();
    }

    /**
     * Меняет периодичность оплаты. Не трогает next_payment_due_at —
     * смена интервала вступает в силу только со следующего платежа.
     */
    public function setBillingInterval(User $user, Course $course, int $days): void
    {
        if (!in_array($days, self::ALLOWED_INTERVALS, true)) {
            throw new \InvalidArgumentException('Unsupported billing interval');
        }

        $pivot = $this->pivotOrFail($user, $course);
        $pivot->billing_interval_days = $days;
        $pivot->save();
    }

    /**
     * Единая точка записи успешного платежа — используется и ручной
     * фиксацией администратором, и (в будущем) обработчиком вебхука шлюза.
     *
     * @param array{
     *   billing_interval_days?: int|null,
     *   paid_at?: \DateTimeInterface|string|null,
     *   currency?: string,
     *   recorded_by_user_id?: int|null,
     *   note?: string|null,
     * } $meta
     */
    public function recordPayment(User $user, Course $course, int $amountCents, string $method, array $meta = []): Payment
    {
        return DB::transaction(function () use ($user, $course, $amountCents, $method, $meta) {
            $pivot = CourseUser::where('user_id', $user->id)->where('course_id', $course->id)->first();

            if (!$pivot) {
                $this->enroll->enrollUser($user, $course, [
                    'source' => $method === 'manual' ? 'manual' : 'payment',
                ]);
                $pivot = CourseUser::where('user_id', $user->id)->where('course_id', $course->id)->firstOrFail();
            }

            $intervalDays = $meta['billing_interval_days'] ?? $pivot->billing_interval_days ?? 30;
            $paidAt = isset($meta['paid_at']) ? Carbon::parse($meta['paid_at']) : now();

            // Новый период всегда считается поверх уже установленной даты платежа,
            // а не от дня фактической оплаты — уроки за период с 5 по 5 число всё
            // равно шли по расписанию, даже если оплатили 10-го. Это верно и для
            // досрочной оплаты, и для небольшого опоздания. Откатываемся к дате
            // оплаты только если старой даты вообще нет (самый первый платёж) или
            // просрочка больше MAX_CATCH_UP_DAYS — порог фиксированный и одинаковый
            // для любой периодичности (в том числе для двухнедельного плана), не
            // привязан к billing_interval_days.
            $baseline = $pivot->next_payment_due_at;
            if ($baseline === null || $baseline->copy()->addDays(self::MAX_CATCH_UP_DAYS)->lt($paidAt)) {
                $baseline = $paidAt;
            }

            $pivot->status = 'active';
            $pivot->billing_interval_days = $intervalDays;
            $pivot->next_payment_due_at = $baseline->copy()->addDays($intervalDays);
            $pivot->promised_payment_used_at = null;
            $pivot->promised_payment_expires_at = null;
            $pivot->reminder_sent_at = null;
            $pivot->save();

            return Payment::create([
                'course_user_id' => $pivot->id,
                'user_id' => $user->id,
                'course_id' => $course->id,
                'amount_cents' => $amountCents,
                'currency' => $meta['currency'] ?? 'RUB',
                'method' => $method,
                'status' => 'succeeded',
                'is_promise' => false,
                'paid_at' => $paidAt,
                'recorded_by_user_id' => $meta['recorded_by_user_id'] ?? null,
                'note' => $meta['note'] ?? null,
            ]);
        });
    }

    public function grantPromisedPayment(User $user, Course $course): Payment
    {
        return DB::transaction(function () use ($user, $course) {
            if (!$this->isPromiseAvailable($user, $course)) {
                throw new \DomainException('Обещанный платёж недоступен для этой записи прямо сейчас.');
            }

            $pivot = $this->pivotOrFail($user, $course);
            $expiresAt = now()->addDays(self::PROMISE_DAYS);

            $pivot->promised_payment_expires_at = $expiresAt;
            $pivot->promised_payment_used_at = now();
            $pivot->save();

            return Payment::create([
                'course_user_id' => $pivot->id,
                'user_id' => $user->id,
                'course_id' => $course->id,
                'amount_cents' => 0,
                'currency' => 'RUB',
                'method' => 'promised',
                'status' => 'succeeded',
                'is_promise' => true,
                'promise_expires_at' => $expiresAt,
                'paid_at' => null,
            ]);
        });
    }

    /**
     * Подключает промокод со скидкой к записи на курс — постоянно, пока не снят.
     * Валидируется один раз, в момент подключения (см. PromoLookup); лимит
     * использований (used_count) увеличивается тоже только сейчас, не на
     * каждый платёжный цикл.
     */
    public function applyPromoCode(User $user, Course $course, string $code): \App\Models\PromoCode
    {
        [$promo, $error] = PromoLookup::find(
            $code,
            $course->id,
            'discount',
            'Этот промокод не даёт скидку на регулярную оплату.'
        );

        if (!$promo) {
            throw new \DomainException($error);
        }

        $pivot = $this->pivotOrFail($user, $course);
        $pivot->promo_code_id = $promo->id;
        $pivot->save();

        $promo->increment('used_count');

        return $promo;
    }

    public function removePromoCode(User $user, Course $course): void
    {
        $pivot = $this->pivotOrFail($user, $course);
        $pivot->promo_code_id = null;
        $pivot->save();
    }

    /**
     * Реальная сумма к оплате за эту запись: базовая цена курса, либо со
     * скидкой, если к записи подключён промокод (PromoPricing — та же чистая
     * функция, что уже применяется в предпросмотре чекаута).
     */
    public function priceForEnrollment(User $user, Course $course): int
    {
        $base = (int) ($course->price_cents ?? 0);

        $pivot = $this->pivot($user, $course);
        if ($pivot && $pivot->promo_code_id && $pivot->promoCode) {
            return PromoPricing::apply($base, $pivot->promoCode);
        }

        return $base;
    }

    private function pivot(User $user, Course $course): ?CourseUser
    {
        return CourseUser::where('user_id', $user->id)->where('course_id', $course->id)->first();
    }

    private function pivotOrFail(User $user, Course $course): CourseUser
    {
        return $this->pivot($user, $course) ?? throw new \DomainException('User is not enrolled in this course.');
    }
}
