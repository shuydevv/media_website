<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseUser;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentConfirmedNotification;
use App\Support\CrmDate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AccessController extends Controller
{
    /**
     * "Доступ до" в /admin/crm выглядит как одно поле с датой (+ необязательная
     * сумма), но реальный доступ (BillingService::hasAccess()) гейтится
     * по-разному в зависимости от типа зачисления:
     *  - обычный биллинг (billing_interval_days задан) — читает
     *    next_payment_due_at/promised_payment_expires_at, expires_at вообще
     *    не смотрит;
     *  - ручное/промо зачисление (billing_interval_days пусто) — читает
     *    expires_at.
     * В обоих случаях доступ ещё и жёстко заперт на status==='active' —
     * без этого дата в любом поле ни на что не влияет и следующий ночной
     * прогон enrollments:expire её же и погасит. Пишем правильное поле сами,
     * а не заставляем админа знать про эту развилку.
     *
     * Сумма необязательна намеренно: продление без неё (доброй волей,
     * исправление ошибки) — не платёж, писать 0 и не уведомлять ученика.
     * Если сумма указана — это то же самое действие, что когда-нибудь будет
     * прилетать из платёжного шлюза автоматически: тот же Payment, тот же
     * PaymentConfirmedNotification, разница только в method ('manual' — от
     * админа, а не сгенерирован системой) и в том, что дату выбирает
     * человек, а не расчёт по интервалу.
     *
     * ПАКЕТНАЯ ЦЕНА (решено 2026-09-02, ученик платит 4400₽ за 1 курс или
     * 6400₽ за 2 — это не 2×4400, скидка за пакет). Этот эндпоинт НЕ знает
     * о пакетах вообще — каждый курс продлевается отдельным вызовом. Админ
     * вручную записывает всю сумму пакета (6400) на ОДИН из двух курсов и
     * оставляет заметку в комментарии ученика ("оплата за оба курса — см.
     * <курс>"), второй курс продлевает отдельным вызовом с суммой 0. Так
     * сделано специально: делить сумму пополам исказило бы её смысл (курс
     * не "стоит" 3200, эта цена существует только в паре), а общая
     * месячная выручка (IndexController::monthlyRevenue()) при этом не
     * задваивается и не теряется — искажается только разбивка по курсам,
     * поэтому её сознательно не показываем отдельно по курсам в CRM.
     * Если ученик перестаёт платить за один из пары — оставшийся курс
     * возвращается к цене 4400, но система это не отслеживает и не
     * подсказывает: это знание сейчас живёт только в голове админа.
     * TODO(автоматический биллинг): когда появится платёжный шлюз, эту
     * логику нельзя будет оставить "в голове" — тогда понадобится настоящая
     * сущность "пакет/бандл" (какие курсы входят, сколько стоит вместе),
     * которая сама пересчитывает цену при отваливании одного из курсов.
     */
    public function __invoke(Request $request, User $user, Course $course)
    {
        $data = $request->validate([
            'access_until' => ['required', 'date'],
            'amount_rub' => ['nullable', 'numeric', 'min:0'],
        ]);

        $until = Carbon::parse($data['access_until'])->endOfDay();
        $amountCents = !empty($data['amount_rub']) ? (int) round($data['amount_rub'] * 100) : 0;

        $pivot = CourseUser::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $payment = DB::transaction(function () use ($pivot, $until, $user, $course, $amountCents) {
            $pivot->status = 'active';

            if ($pivot->billing_interval_days === null) {
                $pivot->expires_at = $until;
            } else {
                $pivot->next_payment_due_at = $until;
                $pivot->promised_payment_used_at = null;
                $pivot->promised_payment_expires_at = null;
                $pivot->reminder_sent_at = null;
                $pivot->overdue_notified_at = null;
                $pivot->promise_expiring_notified_at = null;
            }

            $pivot->save();

            return Payment::create([
                'course_user_id' => $pivot->id,
                'user_id' => $user->id,
                'course_id' => $course->id,
                'amount_cents' => $amountCents,
                'currency' => 'RUB',
                'method' => 'manual',
                'status' => 'succeeded',
                'is_promise' => false,
                'paid_at' => now(),
                'recorded_by_user_id' => auth()->id(),
                'note' => $amountCents > 0
                    ? 'CRM: платёж записан вручную, доступ продлён до '.$until->format('d.m.Y')
                    : 'CRM: доступ продлён вручную до '.$until->format('d.m.Y'),
            ]);
        });

        if ($amountCents > 0) {
            $user->notify(new PaymentConfirmedNotification($payment));
        }

        // Продление доступа/платёж могли поменять итоговый статус (Новый →
        // Активный, Заморожен → Активный и т.п.) — courses уже загруженная
        // коллекция на $user, перезагружаем, иначе crmStatus() увидит старый
        // pivot и вернёт то же значение, что было до сохранения.
        $user->load('courses');

        return response()->json([
            'ok' => true,
            'access_until' => CrmDate::format($until),
            'payment' => $amountCents > 0 ? [
                'amount_rub' => number_format($amountCents / 100, 0, ',', ' '),
                'paid_at' => CrmDate::format(now()),
            ] : null,
            'status' => $user->crmStatusPayload(),
        ]);
    }
}
