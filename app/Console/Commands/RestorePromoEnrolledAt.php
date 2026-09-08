<?php

namespace App\Console\Commands;

use App\Models\CourseUser;
use App\Models\PromoRedemption;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;

/**
 * Чинит побочный эффект бага в EnrollmentService::enrollUser() (см. коммит,
 * который его исправил): повторный вызов enrollUser() на уже существующем
 * зачислении (например, Admin\User\UpdateController при ручной реактивации
 * доступа) затирал course_user.enrolled_at на now(), из-за чего
 * Homework::isLessonBeforeEnrollment() прятал все домашки от уроков до этой
 * даты — включая уже сделанные. Сами submissions при этом не удалялись,
 * только становились недостижимы через UI.
 *
 * Источник истинной даты — promo_redemptions.enrolled_at, эту таблицу баг
 * не трогал (она пишется один раз в RedeemController::redeem() и больше
 * никогда не обновляется).
 *
 * По умолчанию — только отчёт (dry-run), ничего не меняет. Запись —
 * только с --force.
 */
class RestorePromoEnrolledAt extends Command
{
    use ConfirmableTrait;

    protected $signature = 'enrollments:restore-promo-dates
        {--user= : Ограничиться одним user_id (для проверки на одном ученике перед массовым прогоном)}
        {--force : Выполнить без интерактивного подтверждения и реально записать изменения}';

    protected $description = 'Найти/исправить course_user.enrolled_at, затёртый повторным enrollUser() поверх promo-зачисления';

    public function handle(): int
    {
        $query = CourseUser::query()
            ->join('promo_redemptions', function ($join) {
                $join->on('promo_redemptions.user_id', '=', 'course_user.user_id')
                    ->on('promo_redemptions.course_id', '=', 'course_user.course_id');
            })
            // Строго "больше" — enrolled_at сдвинулся вперёд во времени.
            // Баг всегда пишет now() (позже исходного редима), легитимных
            // причин у enrolled_at стать позже даты promo-редима нет.
            ->whereColumn('course_user.enrolled_at', '>', 'promo_redemptions.enrolled_at')
            ->select([
                'course_user.id as course_user_id',
                'course_user.user_id',
                'course_user.course_id',
                'course_user.enrolled_at as current_enrolled_at',
                'promo_redemptions.enrolled_at as original_enrolled_at',
            ]);

        if ($userId = $this->option('user')) {
            $query->where('course_user.user_id', $userId);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->info('Пострадавших записей не найдено.');
            return self::SUCCESS;
        }

        $this->table(
            ['course_user_id', 'user_id', 'course_id', 'сейчас (испорчено)', 'вернём (из promo_redemptions)'],
            $rows->map(fn ($r) => [
                $r->course_user_id,
                $r->user_id,
                $r->course_id,
                $r->current_enrolled_at,
                $r->original_enrolled_at,
            ])
        );

        $this->info("Найдено записей: {$rows->count()}");

        if (!$this->option('force')) {
            $this->comment('Это dry-run. Ничего не изменено. Запустите с --force, чтобы применить.');
            return self::SUCCESS;
        }

        if (!$this->confirmToProceed("Записать {$rows->count()} исправленных enrolled_at в course_user?")) {
            return self::FAILURE;
        }

        // Каждая строка обновляется отдельным UPDATE по id — так безопаснее,
        // чем один массовый UPDATE ... JOIN: любая ошибка на одной строке не
        // мешает остальным, и это видно построчно в выводе. DB::transaction()
        // здесь не даёт настоящей атомарности — course_user, как и почти вся
        // БД, на MyISAM (см. CLAUDE.md), транзакции для неё no-op.
        $updated = 0;
        foreach ($rows as $row) {
            DB::table('course_user')
                ->where('id', $row->course_user_id)
                ->update([
                    'enrolled_at' => $row->original_enrolled_at,
                    'updated_at' => now(),
                ]);
            $updated++;
        }

        $this->info("Обновлено записей: {$updated}");

        return self::SUCCESS;
    }
}
