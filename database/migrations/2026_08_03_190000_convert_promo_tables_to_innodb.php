<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Вся БД была на MyISAM (унаследовано от исходной установки — миграции
 * никогда явно не задавали engine). MyISAM не поддерживает ни транзакции
 * (COMMIT/ROLLBACK — no-op), ни row-level locking: DB::transaction() +
 * lockForUpdate() в RedeemController::redeem() и
 * BillingService::applyPromoCode() выглядели как защита от гонки за
 * max_uses, но SELECT ... FOR UPDATE на MyISAM ничего не блокирует — два
 * параллельных запроса всё ещё могли одновременно пройти проверку лимита.
 * Конвертируем только таблицы, реально задействованные в промокод-флоу;
 * FOREIGN KEY на этих таблицах в момент создания были на MyISAM тихо
 * проигнорированы движком (MySQL не ошибается, просто не создаёт
 * constraint), поэтому чистый ALTER ENGINE не упадёт на "orphan" данных —
 * constraint'ов, которые могли бы это провалидировать, никогда не было.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `promo_codes` ENGINE = InnoDB');
        DB::statement('ALTER TABLE `promo_redemptions` ENGINE = InnoDB');
        DB::statement('ALTER TABLE `course_user` ENGINE = InnoDB');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `promo_codes` ENGINE = MyISAM');
        DB::statement('ALTER TABLE `promo_redemptions` ENGINE = MyISAM');
        DB::statement('ALTER TABLE `course_user` ENGINE = MyISAM');
    }
};
