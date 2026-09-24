<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ученики жаловались на зависания и потерю ответов в визарде домашки
 * (Student\SubmissionController::persistAnswerAndAdvance()). Корень —
 * `submissions` была на MyISAM (унаследовано, см. 2026_08_03_190000_
 * convert_promo_tables_to_innodb.php — там сконвертировали только
 * промокод-таблицы): MyISAM блокирует ВСЮ таблицу на каждую запись, а не
 * строку, из-за чего параллельные сохранения ответов РАЗНЫХ учеников
 * встают в очередь друг за другом (ощущается как зависание под нагрузкой),
 * и — вместе с отсутствием блокировки на уровне приложения — окно гонки
 * при двойном клике/двух вкладках было особенно широким.
 *
 * `users` конвертируем тем же ALTER — App\Service\FishFoodService::
 * withLockedUser() уже многие месяцы полагается на `lockForUpdate()` и
 * даже прокомментирован как "users — InnoDB", но миграции это не
 * подтверждали: на MyISAM это был тихий no-op, тот же паттерн бага, что и
 * был у промокодов до 2026_08_03_190000.
 *
 * FOREIGN KEY на обеих таблицах при создании были на MyISAM тихо
 * проигнорированы движком (см. комментарий в convert_promo_tables_to_
 * innodb.php) — чистый ALTER ENGINE не упадёт на "orphan"-данных,
 * constraint'ов, которые могли бы это провалидировать, никогда не было.
 *
 * ALTER TABLE ... ENGINE на непустой таблице перестраивает её целиком и
 * держит блокировку, пока не закончит — на проде это стоит гнать в окно с
 * низкой нагрузкой, не во время активной сдачи домашек.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `submissions` ENGINE = InnoDB');
        DB::statement('ALTER TABLE `users` ENGINE = InnoDB');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `submissions` ENGINE = MyISAM');
        DB::statement('ALTER TABLE `users` ENGINE = MyISAM');
    }
};
