<?php

namespace App\Http\Controllers\Admin\Crm\Payment;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseUser;
use App\Models\Payment;
use App\Models\User;
use App\Service\BillingService;
use App\Support\CrmCourseState;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Галочка «Оплатил» → окно с суммой и датой оплаты → «ОК». Срок продления
 * считает BillingService (от прежней даты платежа, а не от дня оплаты),
 * а не админ — в отличие от ручной смены даты в AccessController.
 */
class StoreController extends Controller
{
    /** Повторный такой же платёж за это время считаем двойным нажатием. */
    private const DUPLICATE_WINDOW_SECONDS = 120;

    public function __invoke(Request $request, User $user, Course $course, BillingService $billing)
    {
        $data = $request->validate([
            'amount_rub' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'paid_at' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.now()->subDays(60)->toDateString()],
            'billing_interval_days' => ['required', Rule::in(BillingService::ALLOWED_INTERVALS)],
        ], [
            'paid_at.before_or_equal' => 'Дата оплаты не может быть в будущем.',
            'paid_at.after_or_equal' => 'Дата оплаты — не раньше чем 60 дней назад.',
        ]);

        $pivot = CourseUser::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $amountCents = (int) round($data['amount_rub'] * 100);

        $duplicate = Payment::where('course_user_id', $pivot->id)
            ->where('method', 'manual')
            ->where('amount_cents', $amountCents)
            ->where('created_at', '>=', now()->subSeconds(self::DUPLICATE_WINDOW_SECONDS))
            ->exists();
        if ($duplicate) {
            return response()->json([
                'message' => 'Такой платёж только что уже внесён. Если это правда второй платёж — подождите пару минут.',
            ], 409);
        }

        // Время — текущее, если оплата сегодня: по нему же сортируется
        // «последний платёж» в карточке.
        $paidAt = Carbon::parse($data['paid_at']);
        $paidAt = $paidAt->isToday() ? now() : $paidAt->setTime(12, 0);

        $billing->recordPayment($user, $course, $amountCents, 'manual', [
            'paid_at' => $paidAt,
            'billing_interval_days' => (int) $data['billing_interval_days'],
            'recorded_by_user_id' => auth()->id(),
            'note' => 'CRM: «Оплатил»',
        ]);

        return response()->json(CrmCourseState::response($user, $course));
    }
}
