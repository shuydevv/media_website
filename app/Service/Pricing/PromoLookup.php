<?php

namespace App\Service\Pricing;

use App\Models\PromoCode;

class PromoLookup
{
    /**
     * Общая валидация промокода — активен/даты действия/лимит использований/
     * нужный kind/область действия по курсу. Не мутирует ничего (used_count
     * инкрементит вызывающий код после успешного применения).
     *
     * @return array{0: ?PromoCode, 1: ?string} [$promo, $error]
     */
    public static function find(string $code, ?int $courseId, string $requiredKind, string $kindMismatchMessage): array
    {
        $promo = PromoCode::where('code', $code)->first();

        if (!$promo) {
            return [null, 'Промокод не найден'];
        }
        if (!$promo->is_active) {
            return [null, 'Промокод неактивен'];
        }
        if ($promo->starts_at && now()->lt($promo->starts_at)) {
            return [null, 'Промокод ещё не начал действовать'];
        }
        if ($promo->ends_at && now()->gt($promo->ends_at)) {
            return [null, 'Срок действия промокода истёк'];
        }
        if (!is_null($promo->max_uses) && $promo->used_count >= $promo->max_uses) {
            return [null, 'Достигнут лимит использований промокода'];
        }
        if ($promo->kind !== $requiredKind) {
            return [null, $kindMismatchMessage];
        }
        if ($courseId !== null && $promo->course_id !== null && (int) $promo->course_id !== $courseId) {
            return [null, 'Этот промокод не действует для этого курса.'];
        }

        return [$promo, null];
    }
}
