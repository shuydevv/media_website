<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `sessions` (2026_07_18_000001_create_sessions_table.php) никогда не задавала
 * engine явно — как и почти вся БД (см. 2026_08_03_190000_convert_promo_tables_
 * to_innodb.php), она осела на MyISAM. StartSession-мидлварь трогает эту
 * таблицу буквально на КАЖДОМ запросе (гость или ученик), а MyISAM не даёт
 * row-level locking — DELETE, который лотерея GC (config/session.php, было
 * 'lottery' => [2, 100]) запускала на случайном запросе, брал лок на ВСЮ
 * таблицу на время выполнения и блокировал сессии всех остальных
 * одновременных посетителей — с их стороны это выглядело как "сайт завис"
 * при обычном заходе или при входе (вход ещё и сам пишет в эту же таблицу
 * через EnforceSessionLimit). `user_id` объявлен как foreignId()->index()
 * БЕЗ ->constrained() — реального FOREIGN KEY на этой таблице нет, поэтому
 * чистый ALTER ENGINE не может упасть на constraint-валидации, как и в
 * миграции промокодов.
 *
 * Сборка мусора сама по себе вынесена из request-цикла в расписание — см.
 * app/Console/Commands/PruneExpiredSessions.php и урезанную lottery в
 * config/session.php — этот ALTER лишь убирает опасность полной блокировки
 * таблицы на время, пока лотерея ещё не выключена везде (deploy) и на
 * случай, если что-то всё же попытается удалить строки не через команду.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `sessions` ENGINE = InnoDB');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `sessions` ENGINE = MyISAM');
    }
};
