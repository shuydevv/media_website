<?php

namespace App\Support;

use App\Models\Course;
use App\Models\Payment;
use App\Models\User;
use App\Service\BillingService;
use Illuminate\Support\Carbon;

/**
 * Состояние строки курса в карточке ученика /admin/crm: дата «до», цвет,
 * последний платёж, галочки «Напомнил»/«Оплатил», кнопка отмены платежа.
 * Одно место расчёта для серверного рендера карточки и для JSON-ответов
 * эндпоинтов CRM, которые перерисовывают блок оплаты без перезагрузки.
 *
 * Ждёт $student с загруженными courses (pivot) и payments (по убыванию paid_at).
 */
class CrmCourseState
{
    /** Сколько дней после внесения платёж ещё можно отменить из CRM. */
    public const UNDO_WINDOW_DAYS = 14;

    public static function for(User $student, Course $course): array
    {
        $pivot = $course->pivot;
        $isSuspended = $pivot->status === 'suspended';
        $isBilling = $pivot->billing_interval_days !== null;
        $rawUntil = $isBilling ? $pivot->next_payment_due_at : $pivot->expires_at;
        $until = $rawUntil ? Carbon::parse($rawUntil) : null;
        $isOverdue = $isSuspended || ($until && $until->isPast());
        $isSoon = $until && !$isOverdue && now()->diffInDays($until) <= User::CRM_SOON_THRESHOLD_DAYS;

        $coursePayments = $student->payments->where('course_id', $course->id);
        $lastPayment = $coursePayments->first();

        // «Последняя оплата» для окна «Оплатил» — без обещанных и промо (там
        // 0 ₽ не потому, что так платят). Настоящий 0 ₽ остаётся: это второй
        // курс пакета, вся сумма которого записана на первый (AccessController).
        $lastRealPayment = $coursePayments->first(fn (Payment $p) => !$p->is_promise && !in_array($p->method, ['promised', 'promo'], true));
        $defaultAmountRub = $lastRealPayment
            ? (int) round($lastRealPayment->amount_cents / 100)
            : (int) round(app(BillingService::class)->priceForEnrollment($student, $course) / 100);

        // Отменить можно только последний внесённый платёж со снимком
        // состояния (BillingService::canRollback() — то же правило на сервере).
        $latest = $coursePayments->sortByDesc('id')->first();
        $undoPayment = $latest
            && !$latest->is_promise
            && is_array($latest->rollback_state)
            && $latest->created_at
            && $latest->created_at->gt(now()->subDays(self::UNDO_WINDOW_DAYS))
                ? $latest : null;

        $remindedAt = $pivot->admin_reminded_at ? Carbon::parse($pivot->admin_reminded_at) : null;

        return [
            'pivot' => $pivot,
            'until' => $until,
            'isSuspended' => $isSuspended,
            'isOverdue' => $isOverdue,
            'isSoon' => $isSoon,
            'dateColor' => $isOverdue ? 'text-rose-600' : ($isSoon ? 'text-amber-600' : 'text-zinc-700'),
            'lastPayment' => $lastPayment,
            'defaultAmountRub' => $defaultAmountRub,
            'lastRealPayment' => $lastRealPayment,
            'intervalDays' => (int) ($pivot->billing_interval_days ?? 30),
            // Дата, от которой окно «Оплатил» считает продление (та же, что
            // берёт BillingService::nextDueAfterPayment()).
            'baselineDate' => $until,
            'remindedAt' => $remindedAt,
            // Галочки — только когда срок подошёл или прошёл и ученик платит
            // сам: при автоплатеже напоминать и вносить руками нечего. Уже
            // поставленное «напомнил» показываем в любом случае.
            'needsAction' => !$pivot->autopay_enabled && ($isOverdue || $isSoon),
            'undoPayment' => $undoPayment,
        ];
    }

    /**
     * JSON для перерисовки строки курса после действия в CRM (дата, оплата,
     * «напомнил», отмена платежа) — данные перечитываются из базы.
     */
    public static function response(User $user, Course $course): array
    {
        $user->load([
            'courses',
            'payments' => fn ($q) => $q->orderByDesc('paid_at'),
        ]);
        $courseWithPivot = $user->courses->firstWhere('id', $course->id);
        $s = self::for($user, $courseWithPivot);

        return [
            'ok' => true,
            'access_until' => CrmDate::format($s['until']) ?? '—',
            'date_class' => 'sans '.$s['dateColor'],
            'suspended' => $s['isSuspended'],
            'billing_html' => view('admin.crm.partials.course-billing', ['s' => $s])->render(),
            'status' => $user->crmStatusPayload(),
        ];
    }
}
