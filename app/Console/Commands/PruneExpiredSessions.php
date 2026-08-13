<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Замена лотерейной сборки мусора сессий (см. config/session.php — 'lottery'
 * теперь почти выключена). Раньше DatabaseSessionHandler чистил `sessions`
 * прямо внутри случайного пользовательского запроса — на MyISAM это брало
 * лок на всю таблицу и вешало сайт для всех остальных посетителей (см.
 * миграцию convert_sessions_table_to_innodb). После перевода на InnoDB
 * лок уже не на всю таблицу, но чистка всё равно не должна быть побочным
 * эффектом чьего-то случайного запроса — переведена в расписание вне
 * пиковых часов (см. App\Console\Kernel::schedule()).
 *
 * Логика удаления — та же, что в родном Illuminate\Session\DatabaseSessionHandler::gc():
 * порог = сейчас минус session.lifetime (в минутах).
 */
class PruneExpiredSessions extends Command
{
    protected $signature = 'sessions:prune';
    protected $description = 'Delete expired session rows (replaces the request-time GC lottery)';

    public function handle(): int
    {
        $lifetimeMinutes = (int) config('session.lifetime', 120);
        $cutoff = now()->subMinutes($lifetimeMinutes)->getTimestamp();

        $table = config('session.table', 'sessions');
        $connection = config('session.connection');

        $query = $connection ? DB::connection($connection)->table($table) : DB::table($table);

        $deleted = $query->where('last_activity', '<=', $cutoff)->delete();

        $this->info("Expired sessions deleted: {$deleted}");

        return self::SUCCESS;
    }
}
