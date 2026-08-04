<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        // На проде уже накопились реальные дубли от старого бага (см. выше)
        // — ALTER TABLE ... UNIQUE падает на "Duplicate entry", если их не
        // убрать сначала. Оставляем самую раннюю запись на пару
        // (promo_code_id, user_id), лишние удаляем и списываем их с
        // used_count — они были начислены по этим же повторным применениям.
        DB::table('promo_redemptions')
            ->select('promo_code_id', 'user_id', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('promo_code_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function ($group) {
                DB::table('promo_redemptions')
                    ->where('promo_code_id', $group->promo_code_id)
                    ->where('user_id', $group->user_id)
                    ->where('id', '!=', $group->keep_id)
                    ->delete();

                DB::table('promo_codes')
                    ->where('id', $group->promo_code_id)
                    ->where('used_count', '>=', $group->cnt - 1)
                    ->decrement('used_count', $group->cnt - 1);
            });

        // Порядок важен: promo_code_id_user_id_index держит FK на promo_code_id.
        // Если сначала dropIndex — MySQL падает с ошибкой 1553 (нельзя снять
        // индекс, пока на нём висит foreign key), поэтому сперва создаём
        // замену, и только потом убираем старый индекс.
        Schema::table('promo_redemptions', function (Blueprint $table) {
            $table->unique(['promo_code_id', 'user_id']);
        });
        Schema::table('promo_redemptions', function (Blueprint $table) {
            $table->dropIndex(['promo_code_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('promo_redemptions', function (Blueprint $table) {
            $table->index(['promo_code_id', 'user_id']);
        });
        Schema::table('promo_redemptions', function (Blueprint $table) {
            $table->dropUnique(['promo_code_id', 'user_id']);
        });
    }
};
