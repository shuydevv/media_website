<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Service\Pricing\PromoLookup;
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
            [$promo, $error] = PromoLookup::find(
                $code,
                $course->id,
                'discount',
                'Этот промокод активирует доступ на /promo/redeem, а не даёт скидку в оплате.'
            );

            if (!$promo) {
                return back()->withErrors(['code' => $error])->withInput();
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
     * Базовая цена курса в копейках (уже хранится в копейках в courses.price_cents).
     */
    private function basePriceCents(Course $course): array
    {
        return [(int) ($course->price_cents ?? 0), 'RUB'];
    }
}
