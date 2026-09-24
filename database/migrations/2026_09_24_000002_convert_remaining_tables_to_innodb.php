<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Заключительный шаг серии MyISAM → InnoDB (после 2026_08_03_190000 —
 * промокод-таблицы, 2026_08_13_000001 — sessions, 2026_09_24_000001 —
 * submissions/users под конкретную жалобу на зависания визарда домашки).
 * MyISAM блокирует ВСЮ таблицу на каждую запись, а не строку — это было
 * источником зависаний на любой таблице, которую одновременно читают и
 * пишут под нагрузкой, не только на тех, что чинили точечно. Переводим
 * остальные таблицы приложения на InnoDB, чтобы убрать этот класс проблем
 * целиком, а не гоняться за каждым следующим "неудобным" местом отдельно.
 *
 * Список таблиц НЕ хардкодим — читаем текущий engine каждой таблицы прямо
 * из information_schema.TABLES на момент прогона миграции (в конкретной
 * БД, DB::getDatabaseName()). Так миграция:
 *  - не разъедется со схемой к моменту фактического деплоя на проде (новые
 *    таблицы, добавленные между написанием этого файла и прогоном
 *    `migrate`, тоже подхватятся);
 *  - не упадёт и ничего не тронет повторно на том, что уже перевели
 *    предыдущие миграции (promo_codes/promo_redemptions/course_user/
 *    sessions/submissions/users) — их ENGINE к этому моменту уже InnoDB,
 *    под фильтр `ENGINE = 'MyISAM'` они не попадут;
 *  - не трогает VIEW (у них ENGINE = NULL в information_schema, под
 *    фильтр не попадают) и системные схемы MySQL (фильтр по
 *    TABLE_SCHEMA = текущая БД).
 *
 * FOREIGN KEY нигде не пострадают: вся схема создавалась на MyISAM, где
 * FK-constraint'ы в CREATE TABLE тихо игнорировались движком (см.
 * комментарий в 2026_08_03_190000_convert_promo_tables_to_innodb.php) —
 * ALTER ENGINE не может упасть на "осиротевших" строках, проверять
 * нечего.
 *
 * На проде — гнать в окно с низкой нагрузкой: ALTER TABLE ... ENGINE
 * блокирует конкретную таблицу на время перестройки (обычно секунды на
 * таком объёме данных, но таблица недоступна, пока идёт). Перед прогоном
 * на проде — бэкап БД, это обычная предосторожность перед любым ALTER на
 * боевых данных, а не специфика именно этой миграции.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->myIsamTables() as $table) {
            DB::statement("ALTER TABLE `{$table}` ENGINE = InnoDB");
        }
    }

    public function down(): void
    {
        // Осознанно необратимо: чтобы откатить именно то, что перевела
        // ИМЕННО эта миграция (а не заодно promo_codes/course_user/
        // sessions/submissions/users, которые были на InnoDB уже ДО неё),
        // нужен отдельный журнал таблиц, обработанных в up() — его здесь
        // нет. Возврат конкретной таблицы на MyISAM, если когда-нибудь
        // понадобится, — отдельная точечная миграция с явным именем
        // таблицы, а не угадывание по текущему состоянию схемы.
        throw new \RuntimeException(
            'Эта миграция необратима одним общим down() — пришлось бы гадать, какие таблицы '.
            'были на MyISAM ДО неё. Откатывай точечными миграциями по конкретным таблицам.'
        );
    }

    /**
     * @return array<int, string>
     */
    private function myIsamTables(): array
    {
        $rows = DB::select(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND ENGINE = 'MyISAM'",
            [DB::getDatabaseName()]
        );

        return collect($rows)->pluck('TABLE_NAME')->values()->all();
    }
};
