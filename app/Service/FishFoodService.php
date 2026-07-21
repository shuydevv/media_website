<?php

namespace App\Service;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Начисление и трата «корма» маскота-рыбы (растёт в акулу по мере роста
 * fish_total_fed). Одна рыба на аккаунт ученика (не на курс) — все поля
 * плоские, прямо на User. Суммы и пороги — в config/fish.php.
 *
 * Разовые бонусы (первая домашка, серия из 5) хранятся флагами в
 * fish_milestones (json), а не отдельными булевыми колонками — так же, как
 * notification_preferences, без новой миграции под каждый будущий бонус.
 */
class FishFoodService
{
    public function awardCorrectAnswer(User $user, ?int $amount = null): void
    {
        $user->increment('fish_corm_balance', $amount ?? config('fish.corm.correct_answer'));
    }

    /**
     * Единая точка начисления всех бонусов за завершённую домашку — вызывается
     * ровно один раз на сабмишен (см. SubmissionController::finishSubmit(),
     * там это гарантированно one-shot: ensureInProgress() не пускает сюда
     * повторно после смены статуса), поэтому доп. флага "уже начислено"
     * на уровне сабмишена не требуется.
     */
    public function awardHomeworkCompletion(User $user, Submission $submission): void
    {
        DB::transaction(function () use ($user, $submission) {
            $corm = config('fish.corm');
            $total = 0;
            $onTime = $submission->status !== 'expired';

            $milestones = $user->fish_milestones ?? [];

            if ($onTime) {
                $total += $corm['homework_on_time'];

                $maxPossible = array_sum(array_column($submission->per_task_results ?? [], 'max'));
                if ($maxPossible > 0 && (int) $submission->total_score === (int) $maxPossible) {
                    $total += $corm['homework_perfect'];
                }

                if ((int) $submission->attempt_no === 1) {
                    $total += $corm['homework_first_attempt'];
                } elseif ((int) $submission->attempt_no >= 2) {
                    $total += $corm['homework_retry_pass'];
                }

                $user->fish_streak_count = (int) $user->fish_streak_count + 1;
                if ($user->fish_streak_count >= 5 && empty($milestones['streak_5'])) {
                    $total += $corm['streak_5_bonus'];
                    $milestones['streak_5'] = true;
                }

                if (empty($milestones['first_homework'])) {
                    $total += $corm['first_homework_ever'];
                    $milestones['first_homework'] = true;
                }
            } else {
                $user->fish_streak_count = 0;
            }

            $user->fish_milestones = $milestones;
            $user->fish_corm_balance = (int) $user->fish_corm_balance + $total;
            $user->save();
        });
    }

    /**
     * Начисляет бонус за визит не чаще раза в календарный день.
     */
    public function awardDailyVisit(User $user): bool
    {
        if ($user->fish_last_active_date !== null && $user->fish_last_active_date->isToday()) {
            return false;
        }

        // Плоское присваивание + save(), не increment(): increment() пишет
        // только свою колонку отдельным запросом и молча не подхватывает
        // другие dirty-атрибуты — fish_last_active_date иначе никогда не
        // попал бы в БД, и guard выше никогда бы не сработал.
        $user->fish_last_active_date = now()->toDateString();
        $user->fish_corm_balance = (int) $user->fish_corm_balance + config('fish.corm.daily_visit');
        $user->save();

        return true;
    }

    /**
     * «Покормить» — тратит 1 корм за нажатие (не весь баланс разом), значит
     * ощутимый прогресс на несколько уровней требует нескольких нажатий.
     * Баланс 0 — no-op (кнопка на фронте тоже должна быть недоступна, но
     * контроллер не полагается только на это).
     */
    public function feed(User $user): array
    {
        $levelBefore = $this->levelFor((int) $user->fish_total_fed);

        if ((int) $user->fish_corm_balance <= 0) {
            return ['fed' => 0, 'level_before' => $levelBefore, 'level_after' => $levelBefore, 'leveled_up' => false];
        }

        $fed = 1;

        DB::transaction(function () use ($user, $fed) {
            $user->fish_total_fed = (int) $user->fish_total_fed + $fed;
            $user->fish_corm_balance = (int) $user->fish_corm_balance - $fed;
            $user->save();
        });

        $levelAfter = $this->levelFor((int) $user->fish_total_fed);

        return [
            'fed' => $fed,
            'level_before' => $levelBefore,
            'level_after' => $levelAfter,
            'leveled_up' => $levelAfter > $levelBefore,
        ];
    }

    /**
     * URL картинки маскота для уровня и состояния (default/correct/
     * partly_correct/wrong/eating). Реальный арт грузится по одному уровню
     * за раз, поэтому это с запасом на неполные комплекты:
     *  1) запрошенное состояние для этого уровня;
     *  2) 'wrong' → пробуем алиас 'incorrect' (на 3 уровне файл так и назван —
     *     расхождение в присланных исходниках);
     *  3) 'default' для этого же уровня, если конкретное состояние не нашлось;
     *  4) старая SVG-заглушка fish-level-{N}.svg — если для уровня вообще
     *     нет персонажа в config('fish.characters') или папка ещё пустая.
     */
    public function mascotImageUrl(int $level, string $state = 'default'): string
    {
        $slug = config("fish.characters.{$level}");

        if ($slug) {
            $states = [$state];
            if ($state === 'wrong') {
                $states[] = 'incorrect';
            }
            if ($state !== 'default') {
                $states[] = 'default';
            }

            foreach ($states as $candidate) {
                $relative = "img/mascot/level-{$level}/{$slug}_{$candidate}.png";
                if (file_exists(public_path($relative))) {
                    return asset($relative);
                }
            }
        }

        return asset("img/fish-level-{$level}.svg");
    }

    public function levelName(int $level): string
    {
        $names = config('fish.level_names');

        return $names[$level - 1] ?? $names[count($names) - 1];
    }

    /**
     * Фон за маскотом. $slug — обычно $user->fish_background; null (ещё не
     * выбирал) — общий дефолт из config('fish.default_background').
     */
    public function backgroundImageUrl(?string $slug = null): string
    {
        $slug = $slug ?? config('fish.default_background');

        return asset("img/mascot/background/{$slug}.jpg");
    }

    /**
     * Слаги фонов, реально лежащих на диске (public/img/mascot/background/*.jpg)
     * — не только те, что уже описаны в config('fish.background_labels'), чтобы
     * новый файл сразу появлялся в выборе без правки конфига. Подпись — из
     * конфига, если есть, иначе сам слаг с большой буквы.
     *
     * @return array<string, string> slug => подпись
     */
    public function availableBackgrounds(): array
    {
        $labels = config('fish.background_labels', []);
        $files = glob(public_path('img/mascot/background/*.jpg')) ?: [];

        $backgrounds = [];
        foreach ($files as $file) {
            $slug = pathinfo($file, PATHINFO_FILENAME);
            $backgrounds[$slug] = $labels[$slug] ?? ucfirst(str_replace('_', ' ', $slug));
        }

        ksort($backgrounds);

        // Дефолтный фон — первым в списке, остальные по алфавиту.
        $default = config('fish.default_background');
        if (isset($backgrounds[$default])) {
            $backgrounds = [$default => $backgrounds[$default]] + $backgrounds;
        }

        return $backgrounds;
    }

    /**
     * Фон бесплатный (config('fish.free_backgrounds')) или уже куплен этим
     * пользователем (User::$fish_unlocked_backgrounds).
     */
    public function isBackgroundUnlocked(User $user, string $slug): bool
    {
        if (in_array($slug, config('fish.free_backgrounds', []), true)) {
            return true;
        }

        return in_array($slug, $user->fish_unlocked_backgrounds ?? [], true);
    }

    /**
     * Слаги фонов, доступные этому пользователю прямо сейчас (бесплатные +
     * купленные) — для рендера выбора в профиле.
     *
     * @return string[]
     */
    public function unlockedBackgroundsFor(User $user): array
    {
        $free = config('fish.free_backgrounds', []);
        $purchased = $user->fish_unlocked_backgrounds ?? [];

        return array_values(array_unique(array_merge($free, $purchased)));
    }

    public function levelFor(int $totalFed): int
    {
        $levels = config('fish.levels');
        $level = 1;

        foreach ($levels as $threshold) {
            if ($totalFed >= $threshold) {
                $level++;
            }
        }

        return $level;
    }

    /**
     * @return array{level: int, current: int, needed: int|null, isMax: bool}
     */
    public function progressFor(int $totalFed): array
    {
        $levels = config('fish.levels');
        $level = $this->levelFor($totalFed);

        if ($level > count($levels)) {
            return ['level' => $level, 'current' => $totalFed, 'needed' => null, 'isMax' => true];
        }

        $prevThreshold = $level > 1 ? $levels[$level - 2] : 0;
        $nextThreshold = $levels[$level - 1];

        return [
            'level' => $level,
            'current' => $totalFed - $prevThreshold,
            'needed' => $nextThreshold - $prevThreshold,
            'isMax' => false,
        ];
    }
}
