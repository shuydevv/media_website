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
 * fish_milestones (json на User) сейчас этим сервисом не пишется — поле
 * зарезервировано под будущие разовые бонусы (по тому же принципу, что и
 * notification_preferences: флаг в json, а не отдельная колонка под каждый).
 */
class FishFoodService
{
    /**
     * Блокирует строку пользователя (SELECT ... FOR UPDATE, users — InnoDB)
     * внутри транзакции и прогоняет $mutator по свежей копии — общая точка
     * для всех операций, трогающих fish_corm_balance/fish_total_fed/
     * fish_unlocked_* (кормление, начисление за домашку/задание/визит, обе
     * покупки в ProfileController). Без этого два параллельных запроса
     * (двойной клик «Покормить», покупка фона и аксессуара одновременно,
     * начисление корма за домашку в момент кормления и т.п.) читают баланс
     * каждый из своей копии $user, и последний save() затирает изменение
     * другого (lost update) — баланс расходится с фактически произошедшими
     * событиями.
     *
     * $mutator получает свежую заблокированную модель, сам решает, что и
     * сохранять, и возвращает результат, который отдаётся вызывающему коду.
     * После выполнения атрибуты копируются обратно в $user, чтобы вызывающий
     * код видел актуальное состояние на уже имеющемся у него объекте.
     */
    private function withLockedUser(User $user, \Closure $mutator)
    {
        return DB::transaction(function () use ($user, $mutator) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();

            $result = $mutator($locked);

            $user->setRawAttributes($locked->getAttributes(), true);
            $user->syncOriginal();

            return $result;
        });
    }

    /**
     * Поддерживает инвариант «корм за задание = балл за задание»: считает
     * дельту между новым баллом ($row['score']) и уже начисленным за это же
     * задание ($row['fish_awarded']) и корректирует баланс на разницу — не
     * разовое начисление, а пересчёт, который одинаково работает и на
     * первую простановку балла, и на любую последующую правку (переответ в
     * визарде, куратор/админ поправил оценку). Баланс не уходит в минус:
     * если оценку снизили уже после того, как корм потрачен на кормление,
     * долг просто не образуется — при следующем повышении баллы досчитаются
     * заново от нуля, а не «в минус».
     *
     * $row — ссылка на строку per_task_results[$taskId]; вызывающий код сам
     * сохраняет submission после того, как разложит изменённые строки обратно.
     */
    public function syncTaskCorm(User $user, array &$row): void
    {
        $newScore = (int) ($row['score'] ?? 0);
        $already  = (int) ($row['fish_awarded'] ?? 0);
        $delta    = $newScore - $already;

        if ($delta !== 0) {
            $this->withLockedUser($user, function (User $locked) use ($delta) {
                $locked->fish_corm_balance = max(0, (int) $locked->fish_corm_balance + $delta);
                $locked->save();
            });
        }

        $row['fish_awarded'] = $newScore;
    }

    /**
     * Единая точка начисления бонуса за завершённую домашку (сверх корма за
     * отдельные задания, см. syncTaskCorm()) — вызывается ровно один раз на
     * сабмишен (см. SubmissionController::finishSubmit(), там это
     * гарантированно one-shot: ensureInProgress() не пускает сюда повторно
     * после смены статуса), поэтому доп. флага "уже начислено" на уровне
     * сабмишена не требуется.
     *
     * Нет ни флэта за сам факт сдачи, ни разового бонуса за первую домашку —
     * единственная награда здесь — homework_on_time_bonus, и только при
     * сдаче не позже due_at. Просрочка (status === 'expired') не даёт ничего
     * сверх уже начисленных баллов за задания.
     */
    public function awardHomeworkCompletion(User $user, Submission $submission): void
    {
        if ($submission->status === 'expired') {
            return;
        }

        $this->withLockedUser($user, function (User $locked) {
            $locked->fish_corm_balance = (int) $locked->fish_corm_balance + config('fish.corm.homework_on_time_bonus');
            $locked->save();
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

        return $this->withLockedUser($user, function (User $locked) {
            // Перепроверяем guard уже под блокировкой строки — без этого два
            // параллельных захода на дашборд в первый визит за день (гонка,
            // не эксплуатируемая для повторного бонуса в тот же день, т.к.
            // оба запроса стартуют от одной и той же 'ещё не сегодня'-базы и
            // пишут одинаковый итог, но по-прежнему lost update по балансу,
            // если между ними влезло кормление/начисление за домашку).
            if ($locked->fish_last_active_date !== null && $locked->fish_last_active_date->isToday()) {
                return false;
            }

            // Плоское присваивание + save(), не increment(): increment() пишет
            // только свою колонку отдельным запросом и молча не подхватывает
            // другие dirty-атрибуты — fish_last_active_date иначе никогда не
            // попал бы в БД, и guard выше никогда бы не сработал.
            $locked->fish_last_active_date = now()->toDateString();
            $locked->fish_corm_balance = (int) $locked->fish_corm_balance + config('fish.corm.daily_visit');
            $locked->save();

            return true;
        });
    }

    /**
     * «Покормить» — тратит 1 корм за нажатие (не весь баланс разом), значит
     * ощутимый прогресс на несколько уровней требует нескольких нажатий.
     * Баланс 0 — no-op (кнопка на фронте тоже должна быть недоступна, но
     * контроллер не полагается только на это).
     */
    public function feed(User $user): array
    {
        if ((int) $user->fish_corm_balance <= 0) {
            $levelBefore = $this->levelFor((int) $user->fish_total_fed);

            return ['fed' => 0, 'level_before' => $levelBefore, 'level_after' => $levelBefore, 'leveled_up' => false];
        }

        return $this->withLockedUser($user, function (User $locked) {
            // Баланс и уровень «до» — тоже под блокировкой, а не из уже
            // загруженного объекта: иначе при гонке (двойной клик, две
            // вкладки) второй запрос мог бы списать корм ниже нуля или
            // посчитать level_before от устаревшего total_fed.
            $levelBefore = $this->levelFor((int) $locked->fish_total_fed);

            if ((int) $locked->fish_corm_balance <= 0) {
                return ['fed' => 0, 'level_before' => $levelBefore, 'level_after' => $levelBefore, 'leveled_up' => false];
            }

            $fed = 1;
            $locked->fish_total_fed = (int) $locked->fish_total_fed + $fed;
            $locked->fish_corm_balance = (int) $locked->fish_corm_balance - $fed;
            $locked->save();

            $levelAfter = $this->levelFor((int) $locked->fish_total_fed);

            return [
                'fed' => $fed,
                'level_before' => $levelBefore,
                'level_after' => $levelAfter,
                'leveled_up' => $levelAfter > $levelBefore,
            ];
        });
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
     * Порядок: сначала бесплатные (дефолтный фон — первым среди них), затем
     * платные по возрастанию цены — ученик сразу видит, что доступно даром,
     * а дальше понятную лестницу "дешевле → дороже", а не вперемешку по
     * алфавиту.
     *
     * @return array<string, string> slug => подпись
     */
    public function availableBackgrounds(): array
    {
        $labels = config('fish.background_labels', []);
        $files = glob(public_path('img/mascot/background/*.jpg')) ?: [];
        $free = config('fish.free_backgrounds', []);
        $default = config('fish.default_background');
        $seasonal = config('fish.seasonal_backgrounds', []);
        $currentMonth = (int) now()->format('n');

        $backgrounds = [];
        foreach ($files as $file) {
            $slug = pathinfo($file, PATHINFO_FILENAME);

            // Сезонный фон (например "Новый год") — не просто заблокирован,
            // а вообще не показывается в выборе за пределами своих месяцев
            // (config('fish.seasonal_backgrounds')), даже если файл уже
            // лежит на диске.
            if (isset($seasonal[$slug]) && !in_array($currentMonth, $seasonal[$slug], true)) {
                continue;
            }

            $backgrounds[$slug] = $labels[$slug] ?? ucfirst(str_replace('_', ' ', $slug));
        }

        uksort($backgrounds, function (string $a, string $b) use ($free, $default) {
            $aFree = in_array($a, $free, true);
            $bFree = in_array($b, $free, true);

            if ($aFree !== $bFree) {
                return $aFree ? -1 : 1;
            }

            if ($a === $default || $b === $default) {
                return $a === $default ? -1 : 1;
            }

            if (!$aFree) {
                $priceDiff = $this->backgroundPrice($a) <=> $this->backgroundPrice($b);
                if ($priceDiff !== 0) {
                    return $priceDiff;
                }
            }

            return strcmp($a, $b);
        });

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

    /**
     * Цена фона по тирам (config('fish.background_prices')) — фолбэк 20
     * (тир "обычный") на случай, если добавили новый фон на диск, но ещё
     * не прописали ему цену в конфиге.
     */
    public function backgroundPrice(string $slug): int
    {
        return (int) (config("fish.background_prices.{$slug}") ?? 20);
    }

    /**
     * Покупка фона — атомарно под блокировкой строки пользователя:
     * ProfileController раньше делал check-then-act (читал баланс из уже
     * загруженной модели, проверял в PHP, потом save()) без транзакции и
     * без лока — два параллельных запроса (например, покупка фона и
     * аксессуара одновременно, или два клика по разным фонам) оба видели
     * один и тот же баланс, оба проходили проверку и оба списывали от одной
     * базы: итог — оба предмета разблокированы, хотя денег хватало на один.
     *
     * @return string 'already_unlocked'|'insufficient'|'purchased'
     */
    public function purchaseBackground(User $user, string $slug): string
    {
        if ($this->isBackgroundUnlocked($user, $slug)) {
            return 'already_unlocked';
        }

        $price = $this->backgroundPrice($slug);

        return $this->withLockedUser($user, function (User $locked) use ($slug, $price) {
            $unlocked = $locked->fish_unlocked_backgrounds ?? [];

            if (in_array($slug, config('fish.free_backgrounds', []), true) || in_array($slug, $unlocked, true)) {
                return 'already_unlocked';
            }

            if ((int) $locked->fish_corm_balance < $price) {
                return 'insufficient';
            }

            $unlocked[] = $slug;

            $locked->fish_corm_balance = (int) $locked->fish_corm_balance - $price;
            $locked->fish_unlocked_backgrounds = array_values(array_unique($unlocked));
            // Сразу выбираем купленный фон — если покупаешь, явно хочешь его применить.
            $locked->fish_background = $slug;
            $locked->save();

            return 'purchased';
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Аксессуары рыбы — вторая косметическая категория, тот же принцип, что
    | и у фонов выше (бесплатный дефолт + платные по тирам), но список задан
    | прямо в конфиге (fish.accessories), а не сканированием диска: реального
    | арта ещё нет, картинок в public/ под них не существует.
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, string> slug => подпись
     */
    public function availableAccessories(): array
    {
        return collect(config('fish.accessories', []))
            ->map(fn (array $a) => $a['label'] ?? ucfirst($a))
            ->all();
    }

    public function accessoryEmoji(string $slug): string
    {
        return (string) (config("fish.accessories.{$slug}.emoji") ?? '❔');
    }

    public function accessoryPrice(string $slug): int
    {
        return (int) (config("fish.accessories.{$slug}.price") ?? 0);
    }

    /**
     * Аксессуар бесплатный (config('fish.free_accessories')) или уже куплен
     * этим пользователем (User::$fish_unlocked_accessories).
     */
    public function isAccessoryUnlocked(User $user, string $slug): bool
    {
        if (in_array($slug, config('fish.free_accessories', ['none']), true)) {
            return true;
        }

        return in_array($slug, $user->fish_unlocked_accessories ?? [], true);
    }

    /**
     * Покупка аксессуара — та же атомарная схема под блокировкой строки
     * пользователя, что и у purchaseBackground() выше (см. комментарий там
     * про lost update при параллельных покупках).
     *
     * @return string 'already_unlocked'|'insufficient'|'purchased'
     */
    public function purchaseAccessory(User $user, string $slug): string
    {
        if ($this->isAccessoryUnlocked($user, $slug)) {
            return 'already_unlocked';
        }

        $price = $this->accessoryPrice($slug);

        return $this->withLockedUser($user, function (User $locked) use ($slug, $price) {
            $unlocked = $locked->fish_unlocked_accessories ?? [];

            if (in_array($slug, config('fish.free_accessories', ['none']), true) || in_array($slug, $unlocked, true)) {
                return 'already_unlocked';
            }

            if ((int) $locked->fish_corm_balance < $price) {
                return 'insufficient';
            }

            $unlocked[] = $slug;

            $locked->fish_corm_balance = (int) $locked->fish_corm_balance - $price;
            $locked->fish_unlocked_accessories = array_values(array_unique($unlocked));
            // Сразу выбираем купленный аксессуар — та же логика, что и у фонов.
            $locked->fish_accessory = $slug;
            $locked->save();

            return 'purchased';
        });
    }

    /**
     * @return string[]
     */
    public function unlockedAccessoriesFor(User $user): array
    {
        $free = config('fish.free_accessories', ['none']);
        $purchased = $user->fish_unlocked_accessories ?? [];

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
