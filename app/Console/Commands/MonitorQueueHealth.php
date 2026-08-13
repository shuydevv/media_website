<?php

namespace App\Console\Commands;

use App\Service\Ops\TelegramAlert;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Ловит два симптома "очередь встала", которые раньше замечали только
 * когда студент напрямую жаловался, что письмо не пришло:
 *   1) самая старая необработанная job в `jobs` висит дольше STALE_MINUTES —
 *      значит воркер (queue:work) не работает вообще (упал/не запущен);
 *   2) появились новые записи в `failed_jobs` — джобы обрабатываются, но
 *      падают (пример из жизни — SMTP-провайдер отключил тариф, все письма
 *      сыпались в failed_jobs, а увидели это только когда студент написал,
 *      что код на почту не пришёл).
 * Алертит через Telegram (TelegramAlert), не письмом — если сломалась
 * именно почта, письмо-алерт никто бы не увидел. Кулдаун на повторный алерт
 * по одной и той же проблеме — REALERT_MINUTES, чтобы не заспамить чат
 * одним и тем же на каждый прогон расписания.
 */
class MonitorQueueHealth extends Command
{
    protected $signature = 'queue:monitor-health';
    protected $description = 'Проверить, не зависла ли очередь и не копятся ли failed_jobs; алертит в Telegram';

    private const STALE_MINUTES = 10;
    private const REALERT_MINUTES = 60;

    public function handle(): int
    {
        $this->checkStaleQueue();
        $this->checkNewFailures();

        return self::SUCCESS;
    }

    private function checkStaleQueue(): void
    {
        $oldestPendingTs = DB::table('jobs')->min('created_at');
        if (!$oldestPendingTs) {
            return;
        }

        $oldestPending = Carbon::createFromTimestamp($oldestPendingTs);
        $ageMinutes = $oldestPending->diffInMinutes(now());

        if ($ageMinutes <= self::STALE_MINUTES) {
            return;
        }

        $this->alertOnce(
            'queue_monitor.stale_alerted_at',
            "⚠️ Очередь Laravel не обрабатывается уже {$ageMinutes} мин (задача с ".$oldestPending->format('d.m H:i')." всё ещё висит в jobs). Похоже, queue:work не работает — проверь `systemctl status poltav-queue`."
        );
    }

    private function checkNewFailures(): void
    {
        $failedCount = DB::table('failed_jobs')->count();
        $lastKnownCount = (int) Cache::get('queue_monitor.failed_count', $failedCount);
        Cache::put('queue_monitor.failed_count', $failedCount, now()->addWeek());

        $newFailures = $failedCount - $lastKnownCount;
        if ($newFailures <= 0) {
            return;
        }

        $lastException = DB::table('failed_jobs')->orderByDesc('id')->value('exception');
        $summary = $lastException ? strtok($lastException, "\n") : 'детали не найдены';

        $this->alertOnce(
            'queue_monitor.failed_alerted_at',
            "⚠️ {$newFailures} новых проваленных job(ов) в очереди (всего failed_jobs: {$failedCount}).\nПоследняя ошибка: {$summary}"
        );
    }

    private function alertOnce(string $cacheKey, string $message): void
    {
        $lastAlertedAt = Cache::get($cacheKey);
        if ($lastAlertedAt && Carbon::parse($lastAlertedAt)->diffInMinutes(now()) < self::REALERT_MINUTES) {
            return;
        }

        TelegramAlert::send($message);
        Cache::put($cacheKey, now()->toDateTimeString(), now()->addDay());
    }
}
