<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\PromoCode;
use App\Service\Pricing\PromoPricing;
use Illuminate\Http\Request;

class CourseCheckoutController extends Controller
{
    public function show(Request $request, Course $course)
    {
        [$baseCents, $baseCurrency] = $this->basePriceCents($course);

        return view('checkout.course', [
            'course'       => $course,
            'baseCents'    => $baseCents,
            'baseCurrency' => $baseCurrency,
            'finalCents'   => $baseCents,
            'appliedPromo' => null,
        ]);
    }

    public function apply(Request $request, Course $course)
    {
        $request->validate([
            'code' => ['nullable','string','max:64'],
        ]);

        [$baseCents, $baseCurrency] = $this->basePriceCents($course);
        $finalCents  = $baseCents;
        $appliedPromo = null;

        $code = trim((string) $request->input('code', ''));
        if ($code !== '') {
            $promo = PromoCode::where('code', $code)->first();

            if (!$promo) {
                return back()->withErrors(['code' => 'Промокод не найден'])->withInput();
            }
            if (!$promo->is_active) {
                return back()->withErrors(['code' => 'Промокод неактивен'])->withInput();
            }
            if ($promo->starts_at && now()->lt($promo->starts_at)) {
                return back()->withErrors(['code' => 'Промокод ещё не начал действовать'])->withInput();
            }
            if ($promo->ends_at && now()->gt($promo->ends_at)) {
                return back()->withErrors(['code' => 'Срок действия промокода истёк'])->withInput();
            }
            if (!is_null($promo->max_uses) && $promo->used_count >= $promo->max_uses) {
                return back()->withErrors(['code' => 'Достигнут лимит использований промокода'])->withInput();
            }

            // если код даёт доступ (access) — его нужно активировать на /promo/redeem
            if ($promo->isAccess()) {
                return back()->withErrors(['code' => 'Этот промокод активирует доступ на /promo/redeem, а не даёт скидку в оплате.'])->withInput();
            }

            // (опционально) проверка валюты для amount/fixed_price
            if (in_array($promo->discount_mode, ['amount','fixed_price'], true)) {
                if ($promo->currency && strtoupper($promo->currency) !== $baseCurrency) {
                    return back()->withErrors(['code' => "Валюта промокода ({$promo->currency}) не совпадает с валютой курса ({$baseCurrency})."])->withInput();
                }
            }

            // применяем скидку
            $finalCents = PromoPricing::apply($baseCents, $promo);
            $appliedPromo = $promo;
        }

        return view('checkout.course', [
            'course'       => $course,
            'baseCents'    => $baseCents,
            'baseCurrency' => $baseCurrency,
            'finalCents'   => $finalCents,
            'appliedPromo' => $appliedPromo,
            'inputCode'    => $code,
        ]);
    }

    /**
     * Достаём базовую цену курса в копейках.
     * В твоей схеме поле courses.price — string. Разбираем число и переводим в копейки.
     * Валюта — RUB по умолчанию (поменяй при мультивалюте).
     */
    private function basePriceCents(Course $course): array
    {
        $raw = (string)($course->price ?? '0');
        // оставляем цифры, точку и запятую; заменяем запятую на точку
        $normalized = str_replace(',', '.', preg_replace('/[^\d,\.]/', '', $raw));
        $float = is_numeric($normalized) ? (float)$normalized : 0.0;
        $cents = (int) round($float * 100);

        // если у тебя есть поле currency у курса — верни его. Пока фиксируем RUB.
        return [$cents, 'RUB'];
    }
}
