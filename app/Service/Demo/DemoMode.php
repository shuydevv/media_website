<?php

namespace App\Service\Demo;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Демо-ученик (users.is_demo) — показ курса покупателям на созвоне.
 * Настройки и лёгкий контент — config/demo.php.
 *
 * Тренажёры о демо ничего не знают: лёгкий набор подставляется в scope
 * active()/forTrainer() моделей контента (см. restrict()), прогресс пишется
 * в обычные таблицы и стирается reset() при выходе и входе
 * (App\Listeners\ResetDemoProgress).
 */
class DemoMode
{
    /**
     * Таблицы прогресса ученика: всё, что копится от его действий.
     * Зачисления на курсы (course_user) и ручной доступ к тренажёрам
     * (*_trainer_users) — не прогресс, их назначает админ, не трогаем.
     * Порядок важен там, где нет каскада: ответы раньше сессий/раундов.
     */
    private const PROGRESS_TABLES = [
        'statement_answers', 'statement_reports', 'statement_sessions', 'user_statements',
        'task_attempts', 'task_reviews', 'task_reports', 'user_tasks', 'task_rounds',
        'term_reports', 'term_sessions', 'user_terms',
        'hist_reports', 'hist_sessions', 'user_hist_events',
        'person_reports', 'person_sessions', 'user_persons',
        'submissions', 'homework_unlocks', 'lesson_views', 'user_activity_days',
        'student_feedback', 'announcement_dismissals',
    ];

    /**
     * Что сдвигает «Наступило завтра»: таблица => колонки с датой.
     * Как в командах terms:shift / dates:shift / persons:shift, только
     * разом по всем тренажёрам: сроки повторения, дни сессий (иначе
     * «сегодняшние» раунды с кормом остались бы сегодняшними) и отметки
     * времени, по которым считаются дневные лимиты и порядок повторов.
     */
    private const DATED_COLUMNS = [
        'user_terms' => ['due_on', 'learned_on', 'last_reviewed_on'],
        'term_sessions' => ['day', 'finished_at', 'created_at'],
        'user_hist_events' => ['due_on', 'learned_on', 'last_reviewed_on'],
        'hist_sessions' => ['day', 'finished_at', 'created_at'],
        'user_persons' => ['due_on', 'learned_on', 'last_reviewed_on'],
        'person_sessions' => ['day', 'finished_at', 'created_at'],
        'user_statements' => ['due_on', 'last_answered_at'],
        'statement_sessions' => ['day', 'finished_at', 'created_at'],
        'user_tasks' => ['due_on', 'last_answered_at'],
        'task_rounds' => ['day', 'finished_at', 'created_at'],
        'task_reviews' => ['sent_at', 'created_at'],
    ];

    /** Вошедший пользователь — демо-ученик. */
    public static function active(): bool
    {
        // Auth::hasUser(), а не check(): scope контента зовётся и там, где
        // гард ещё не поднимал пользователя (консоль, очередь) — не надо
        // ради этого лезть в сессию.
        if (! Auth::hasUser()) {
            return false;
        }

        $user = Auth::user();

        return $user instanceof User && (bool) $user->is_demo;
    }

    /**
     * Сужает выборку контента до лёгкого набора, если вошёл демо-ученик.
     * $kind — ключ списка в config/demo.php, $column — колонка с ключом
     * из JSON-базы (slug, key, external_id).
     */
    public static function restrict(Builder $query, string $kind, string $column): Builder
    {
        if (! self::active()) {
            return $query;
        }

        $keys = (array) config("demo.{$kind}", []);
        if (! $keys) {
            return $query;
        }

        return $query->whereIn($query->getModel()->qualifyColumn($column), $keys);
    }

    /** Стирает прогресс и ставит рыбу в стартовое состояние. */
    public function reset(User $user): void
    {
        if (! $user->is_demo) {
            return;
        }

        DB::transaction(function () use ($user) {
            foreach (self::PROGRESS_TABLES as $table) {
                DB::table($table)->where('user_id', $user->id)->delete();
            }

            // Прямой UPDATE, а не save() модели: у вызывающего кода может
            // быть устаревшая копия пользователя (например, гард при выходе),
            // и save() по «грязным» полям молча ничего бы не записал.
            $fish = config('demo.fish');
            User::whereKey($user->id)->update([
                'fish_total_fed' => (int) $fish['total_fed'],
                'fish_corm_balance' => (int) $fish['corm'],
                // Сегодняшний бонус за визит уже «получен» — иначе первый
                // заход на дашборд добавил бы корма сверх пресета.
                'fish_last_active_date' => now()->toDateString(),
                'fish_milestones' => null,
                'fish_name' => null,
                'fish_background' => null,
                'fish_unlocked_backgrounds' => null,
                'fish_accessory' => null,
                'fish_unlocked_accessories' => null,
                'fish_food' => null,
                'fish_unlocked_foods' => null,
            ]);
        });

        $user->refresh();
    }

    /**
     * «Наступило завтра»: всё датированное в прогрессе уезжает на день
     * назад — карточки, назначенные на завтра, ждут повторения сегодня,
     * дневные раунды с кормом снова доступны, бонус за визит — тоже.
     * Строк у демо-ученика мало, поэтому по одной в PHP: без
     * DATE_SUB, который есть не во всех СУБД (тесты — на SQLite).
     */
    public function nextDay(User $user): void
    {
        if (! $user->is_demo) {
            return;
        }

        DB::transaction(function () use ($user) {
            foreach (self::DATED_COLUMNS as $table => $columns) {
                foreach (DB::table($table)->where('user_id', $user->id)->get() as $row) {
                    $changes = [];
                    foreach ($columns as $column) {
                        $value = $row->{$column} ?? null;
                        if ($value !== null) {
                            $moved = Carbon::parse($value)->subDay();
                            $changes[$column] = strlen((string) $value) <= 10
                                ? $moved->toDateString()
                                : $moved->toDateTimeString();
                        }
                    }
                    if ($changes) {
                        DB::table($table)->where('id', $row->id)->update($changes);
                    }
                }
            }

            if ($user->fish_last_active_date !== null) {
                $user->fish_last_active_date = $user->fish_last_active_date->copy()->subDay();
                $user->save();
            }
        });
    }

    /** Что показать на /demo. */
    public function summary(User $user): array
    {
        $today = now()->toDateString();
        $due = 0;
        foreach (['user_terms', 'user_hist_events', 'user_persons', 'user_statements', 'user_tasks'] as $table) {
            $due += DB::table($table)->where('user_id', $user->id)->whereNotNull('due_on')->whereDate('due_on', '<=', $today)->count();
        }

        return [
            'learned' => [
                'Термины' => DB::table('user_terms')->where('user_id', $user->id)->count(),
                'Даты' => DB::table('user_hist_events')->where('user_id', $user->id)->count(),
                'Личности' => DB::table('user_persons')->where('user_id', $user->id)->count(),
                'Суждения' => DB::table('user_statements')->where('user_id', $user->id)->count(),
                'Задания' => DB::table('user_tasks')->where('user_id', $user->id)->count(),
            ],
            'due' => $due,
        ];
    }
}
