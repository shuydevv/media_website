<?php

namespace App\Service\Pricing;

use App\Models\PromoCode;
use InvalidArgumentException;

class PromoPricing
{
    /**
     * Рассчитать итоговую цену в копейках с учётом промокода.
     *
     * @param int $basePriceCents  исходная цена в копейках
     * @param PromoCode $promo
     * @return int итоговая цена в копейках (не ниже 0)
     */
    public static function apply(int $basePriceCents, PromoCode $promo): int
    {
        if (!$promo->isDiscount()) {
            // На всякий случай: коды доступа нельзя применять как скидку
            return $basePriceCents;
        }

        // FREE — итог 0
        if ($promo->discount_mode === 'free') {
            return 0;
        }

        // PERCENT — процент от базовой
        if ($promo->discount_mode === 'percent') {
            $percent = (int)$promo->discount_percent;
            if ($percent < 1 || $percent > 100) {
                throw new InvalidArgumentException('Некорректный процент скидки');
            }
            $discount = intdiv($basePriceCents * $percent, 100);
            return max(0, $basePriceCents - $discount);
        }

        // AMOUNT — минус фикс. сумма
        if ($promo->discount_mode === 'amount') {
            $value = (int)$promo->discount_value_cents;
            return max(0, $basePriceCents - $value);
        }

        // FIXED_PRICE — зафиксированная цена
        if ($promo->discount_mode === 'fixed_price') {
            $value = (int)$promo->discount_value_cents; // тут это «итоговая цена»
            return max(0, $value);
        }

        // на всякий случай
        return $basePriceCents;
    }
}
