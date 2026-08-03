<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Раньше был только обычный (не уникальный) индекс [promo_code_id, user_id]
 * — ничто на уровне БД не мешало одному пользователю применить один и тот
 * же access-промокод повторно (RedeemController::redeem() не проверял
 * существующий PromoRedemption), каждый раз тратя общий лимит max_uses,
 * рассчитанный на разных студентов.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_redemptions', function (Blueprint $table) {
            $table->dropIndex(['promo_code_id', 'user_id']);
            $table->unique(['promo_code_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('promo_redemptions', function (Blueprint $table) {
            $table->dropUnique(['promo_code_id', 'user_id']);
            $table->index(['promo_code_id', 'user_id']);
        });
    }
};
