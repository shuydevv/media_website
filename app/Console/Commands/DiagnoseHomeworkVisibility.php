<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\CourseUser;
use App\Models\Homework;
use App\Models\PromoRedemption;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Диагностика "почему ученик не видит домашку" — печатает всё, от чего
 * зависит Homework::isLessonBeforeEnrollment()/isOverdueFor() для конкретной
 * пары ученик+курс, плюс сверяет, действительно ли на сервере задеплоен
 * код с фиксом (см. коммит "Fix promo students losing access to old
 * homeworks after manual reactivation") — самая частая причина "задеплоил,
 * а ничего не изменилось" это то, что деплой не подхватил новый код
 * (opcache, не тот release, деплой упал раньше переключения симлинка и т.п.),
 * а не то, что фикс не работает.
 *
 * Только чтение, ничего не меняет.
 */
class DiagnoseHomeworkVisibility extends Command
{
    protected $signature = 'homework:diagnose
        {user : ID или email ученика}
        {--course= : ID курса (если не указан — все курсы ученика)}';

    protected $description = 'Показать, почему ученику не видны/видны домашки конкретного курса';

    public function handle(): int
    {
        $this->checkDeployedCode();

        $user = is_numeric($this->argument('user'))
            ? User::find((int) $this->argument('user'))
            : User::where('email', $this->argument('user'))->first();

        if (!$user) {
            $this->error('Ученик не найден (по id или email).');
            return self::FAILURE;
        }

        $this->info("Ученик: #{$user->id} {$user->name} <{$user->email}>");

        $courseIds = $this->option('course')
            ? [(int) $this->option('course')]
            : CourseUser::where('user_id', $user->id)->pluck('course_id')->all();

        if ($courseIds === []) {
            $this->warn('У ученика нет ни одного зачисления (course_user).');
            return self::SUCCESS;
        }

        foreach ($courseIds as $courseId) {
            $this->diagnoseCourse($user, $courseId);
        }

        return self::SUCCESS;
    }

    private function checkDeployedCode(): void
    {
        $this->line('--- Проверка задеплоенного кода ---');

        $commit = trim((string) shell_exec('cd '.escapeshellarg(base_path()).' && git rev-parse HEAD 2>&1'));
        $this->line("git HEAD в этом релизе: {$commit}");

        $userModelSource = file_get_contents((new \ReflectionClass(User::class))->getFileName());
        $hasFix = str_contains($userModelSource, 'PromoRedemption');

        if ($hasFix) {
            $this->info('✅ Фикс courseEnrolledAt() (самоисцеление по promo_redemptions) НАЙДЕН в загруженном коде.');
        } else {
            $this->error('❌ Фикс НЕ найден в текущем коде User.php — этот сервер обслуживает СТАРУЮ версию.');
            $this->error('   Скорее всего, деплой либо не запускался после пуша, либо переключился на релиз без этого коммита,');
            $this->error('   либо opcache/php-fpm отдаёт закэшированный код. Дальше по коду смысла нет — сначала разберитесь с этим.');
        }

        $this->newLine();
    }

    private function diagnoseCourse(User $user, int $courseId): void
    {
        $course = Course::find($courseId);
        $this->line("=== Курс #{$courseId} ".($course->title ?? '(не найден)').' ===');

        $pivotRaw = CourseUser::where('user_id', $user->id)->where('course_id', $courseId)->first();

        if (!$pivotRaw) {
            $this->warn('  course_user запись отсутствует — ученик не зачислен на этот курс.');
            return;
        }

        $this->table(
            ['status', 'enrolled_at (raw)', 'created_at', 'expires_at', 'source', 'promo_code', 'billing_interval_days'],
            [[
                $pivotRaw->status,
                $pivotRaw->enrolled_at,
                $pivotRaw->created_at,
                $pivotRaw->expires_at,
                $pivotRaw->source,
                $pivotRaw->promo_code,
                $pivotRaw->billing_interval_days,
            ]]
        );

        $promoRows = PromoRedemption::where('user_id', $user->id)->where('course_id', $courseId)->get();

        if ($promoRows->isEmpty()) {
            $this->warn('  Нет строк в promo_redemptions для этой пары user+course — самоисцеление сработать не может (нечем чинить), проверяйте курс/expires_at/status вручную.');
        } else {
            $this->table(
                ['promo_redemption.enrolled_at', 'expires_at', 'promo_code_id'],
                $promoRows->map(fn ($r) => [$r->enrolled_at, $r->expires_at, $r->promo_code_id])->all()
            );
        }

        $computedEnrolledAt = $user->courseEnrolledAt($courseId);
        $this->info("  => User::courseEnrolledAt() ВЕРНУЛ: {$computedEnrolledAt}");

        $homeworks = Homework::where('course_id', $courseId)
            ->with('lesson.courseSession')
            ->get();

        $rows = $homeworks->map(function (Homework $hw) use ($user) {
            $session = $hw->lesson?->courseSession;
            return [
                $hw->id,
                mb_substr($hw->title ?? '', 0, 30),
                $session?->start_date_time?->format('Y-m-d H:i') ?? '—',
                $hw->isLessonUpcoming() ? 'да' : 'нет',
                $hw->isLessonBeforeEnrollment($user) ? 'ДА (скрыта)' : 'нет',
                $hw->isOverdueFor($user) ? 'да' : 'нет',
                $hw->isUnlockedFor($user) ? 'да' : 'нет',
                \App\Models\Submission::where('homework_id', $hw->id)->where('user_id', $user->id)->count(),
            ];
        });

        $this->table(
            ['id', 'title', 'дата урока', 'ещё не наступил', 'скрыта (before enrollment)', 'просрочена', 'точечно открыта', 'submissions'],
            $rows->all()
        );

        $this->newLine();
    }
}
