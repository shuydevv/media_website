<?php

namespace App\Service\Trainers;

use App\Support\Text;

/**
 * Страница «Качалка»: залы из config('trainers.halls') с их
 * станциями, как их видит конкретный ученик. Статусы тренажёров берутся из
 * уже посчитанного TrainerHub::forUser() — здесь только раскладка по залам
 * и подписи. Тон подписей — как у рыбы: «мы», без рода.
 */
class TrainerGym
{
    // Чем срочнее ситуация, тем раньше тренажёр в «Заданиях дня» и тем
    // скорее тренер в зале заговорит именно о нём.
    private const URGENCY = [
        Trainer::UNFINISHED => 5,
        Trainer::OVERDUE => 4,
        Trainer::REVIEW => 3,
        Trainer::NEW_NEAR_LEVEL => 2,
        Trainer::NEW => 1,
        Trainer::DONE => 0,
    ];

    /**
     * @param array $hub результат TrainerHub::forUser()
     * @param ?string $requested зал из ?hall=
     * @return array{halls: array, active: string, quests: array}
     *   halls — [key => {key, title, tab, accent, background, background_phone, filter,
     *   locked, locked_note, pending, opened, total, speech, lead, stations}];
     *   station — {title, emoji, tagline, about, image, x, y, mx, my, scale, size, msize, state
     *   (open|soon|locked), url, pending, situation, badge, note, cta, say};
     *   quests — открытые ученику тренажёры всех залов, срочные первыми;
     *   plan — «План на сегодня» из хаба (null — норма не выбрана).
     */
    public function build(array $hub, ?string $requested = null): array
    {
        $available = [];
        foreach ($hub['trainers'] as $t) {
            $available[get_class($t['trainer'])] = $t;
        }

        $halls = [];
        $quests = [];

        foreach (config('trainers.halls', []) as $key => $hall) {
            $stations = [];
            $hasTrainers = false;

            foreach ($hall['stations'] as $station) {
                $class = $station['trainer'] ?? null;
                // Снаряды «в разработке» скрыты, пока выключен trainers.show_soon.
                if ($class === null && !config('trainers.show_soon', true)) {
                    continue;
                }
                $hasTrainers = $hasTrainers || $class !== null;
                $entry = $available[$class] ?? null;

                $item = [
                    'title' => $station['title'] ?? ($class ? app($class)->title() : ''),
                    'tagline' => $station['tagline'] ?? null,
                    'emoji' => $station['emoji'] ?? null,
                    'about' => $station['about'] ?? null,
                    'image' => !empty($station['image']) ? asset($station['image']) : null,
                    'x' => (float) $station['x'],
                    'y' => (float) $station['y'],
                    // Мини-локация на телефоне — своя расстановка.
                    'mx' => (float) ($station['mx'] ?? $station['x']),
                    'my' => (float) ($station['my'] ?? $station['y']),
                    'scale' => (float) ($station['scale'] ?? 1),
                    // Размер самой картинки снаряда (табличка не растёт).
                    'size' => (float) ($station['size'] ?? 1),
                    'msize' => (float) ($station['msize'] ?? $station['size'] ?? 1),
                    // Без тени на полу (картинка, у которой «пол» уже нарисован
                    // или тень лишняя) и насколько табличка заходит на снаряд:
                    // доля высоты картинки, 0 — табличка над снарядом.
                    'shadow' => (bool) ($station['shadow'] ?? true),
                    'tag_overlap' => (float) ($station['tag_overlap'] ?? 0),
                    'state' => $class === null ? 'soon' : ($entry ? 'open' : 'locked'),
                    'url' => $entry['url'] ?? null,
                    'pending' => 0,
                    'situation' => null,
                    'badge' => null,
                    'note' => $station['about'] ?? null,
                    'cta' => null,
                ];

                if ($entry) {
                    $item = array_merge($item, $this->describe($entry));
                    $quests[] = $item + ['hall' => $key, 'hall_tab' => $hall['tab']];
                }

                $stations[] = $item;
            }

            $open = array_filter($stations, fn ($s) => $s['state'] === 'open');
            $locked = $hasTrainers && !$open;

            $talk = $this->speech($open, $locked, $hall);

            $halls[$key] = [
                'key' => $key,
                'title' => $hall['title'],
                'tab' => $hall['tab'],
                'short' => $hall['short'] ?? $hall['tab'],
                'accent' => $hall['accent'] ?? '#2563EB',
                'background' => !empty($hall['background']) ? $this->versioned($hall['background']) : null,
                // Вертикальный фон для телефона: в горизонтальном, обрезанном
                // до квадрата, предметы на стене выходили крупнее снарядов.
                'background_phone' => !empty($hall['background_phone']) ? $this->versioned($hall['background_phone']) : null,
                'filter' => $hall['filter'] ?? null,
                'locked' => $locked,
                'locked_note' => $hall['locked_note'] ?? 'Зал пока закрыт.',
                'pending' => array_sum(array_column($open, 'pending')),
                'opened' => count($open),
                'total' => count($stations),
                'speech' => $talk['speech'],
                'lead' => $talk['lead'],
                // Ближние к зрителю снаряды рисуются поверх дальних.
                'stations' => collect($stations)->sortBy('y')->values()->all(),
            ];
        }

        usort($quests, fn ($a, $b) => self::URGENCY[$b['situation']] <=> self::URGENCY[$a['situation']]);

        return [
            'halls' => $halls,
            'active' => $this->activeHall($halls, $requested),
            'quests' => $quests,
            'plan' => $hub['plan'] ?? null,
        ];
    }

    /** Подписи открытого тренажёра по его ситуации. */
    private function describe(array $entry): array
    {
        $status = $entry['status'];
        $words = $entry['trainer']->words();
        $count = (int) $status['count'];
        $what = Text::plural($count, $words['what']);

        $newWhat = Text::plural($count, $words['new_what']);

        // say — реплика тренера, когда этот тренажёр в зале самый срочный.
        // Заканчивается призывом — последнее слово в карточке тренера
        // становится ссылкой в тренажёр. Глагол с числом не согласуем
        // («1 термин ждут» — нельзя), поэтому число стоит после глагола.
        $say = match ($status['situation']) {
            Trainer::UNFINISHED => 'Мы остановились на середине — осталось '.$what.'. Продолжим?',
            Trainer::OVERDUE => 'Накопилось на повторение: '.$what.', часть — со вчера. Повторим?',
            Trainer::REVIEW => 'На повторение — '.$what.', это пара минут. Повторим?',
            Trainer::NEW_NEAR_LEVEL => 'Сегодня — '.$newWhat.', и до нового уровня совсем чуть-чуть. Начнём?',
            Trainer::NEW => 'Сегодня — '.$newWhat.', за них будет корм. Начнём?',
            default => null,
        };

        [$note, $quest, $cta] = match ($status['situation']) {
            Trainer::UNFINISHED => ['Мы остановились на середине — осталось '.$what, 'Доделать: '.$what, 'Продолжить'],
            Trainer::OVERDUE => ['Пора повторить '.$what.' — часть ждёт со вчера', 'Повторить '.$what, 'Повторить'],
            Trainer::REVIEW => ['Пора повторить: '.$what, 'Повторить '.$what, 'Повторить'],
            Trainer::NEW_NEAR_LEVEL => ['Можно учить: '.Text::plural($count, $words['new_what']).'. До нового уровня совсем чуть-чуть', 'Выучить '.Text::plural($count, $words['new_what']), 'Учить'],
            Trainer::NEW => ['Можно учить: '.Text::plural($count, $words['new_what']), 'Выучить '.Text::plural($count, $words['new_what']), 'Учить'],
            // note — своя подпись тренажёра для «всё сделано» (у карточек
            // суждений: сколько ещё сессий с кормом), см. Trainer::statusFor().
            default => [
                $status['note'] ?? 'Сегодня всё сделано'.($status['next'] ? '. Завтра на повторение — '.Text::plural((int) $status['next'], $words['what']) : ''),
                'Сделано на сегодня',
                'Открыть',
            ],
        };

        return [
            'pending' => (int) $status['pending'],
            'situation' => $status['situation'],
            // Счётчик на снаряде: сколько ждёт; «…» — начато и брошено.
            'badge' => $status['situation'] === Trainer::DONE ? null : ($count > 0 ? (string) $count : '…'),
            'note' => $note,
            'quest' => $quest,
            'cta' => $cta,
            'say' => $say,
        ];
    }

    /**
     * Что говорит тренер в зале и куда ведёт кнопка под репликой.
     *
     * @return array{speech: string, lead: ?string}
     *   lead — ссылка в самый срочный тренажёр зала (на неё ведёт последнее
     *   слово реплики, «Продолжим?»); null —
     *   звать некуда (зал закрыт, пуст или всё сделано).
     */
    private function speech(array $open, bool $locked, array $hall): array
    {
        if ($locked) {
            return ['speech' => 'Сюда пока не пускают. '.($hall['locked_note'] ?? ''), 'lead' => null];
        }
        if (!$open) {
            return ['speech' => 'Снаряды для этого зала ещё монтируем — скоро откроем!', 'lead' => null];
        }

        usort($open, fn ($a, $b) => self::URGENCY[$b['situation']] <=> self::URGENCY[$a['situation']]);
        $top = $open[0];

        if ($top['situation'] === Trainer::DONE) {
            return ['speech' => 'В этом зале на сегодня всё — отличная тренировка!', 'lead' => null];
        }

        return [
            'speech' => $top['say'],
            'lead' => $top['url'],
        ];
    }

    /**
     * Какой зал открыть: из ?hall=, иначе открытый зал, где больше дел,
     * иначе первый открытый, иначе первый.
     */
    private function activeHall(array $halls, ?string $requested): string
    {
        if ($requested !== null && isset($halls[$requested])) {
            return $requested;
        }

        $open = array_filter($halls, fn ($h) => !$h['locked']);
        if ($open) {
            uasort($open, fn ($a, $b) => $b['pending'] <=> $a['pending']);

            return array_key_first($open);
        }

        return array_key_first($halls) ?? 'history';
    }

    /**
     * Адрес картинки с версией по времени изменения файла: картинку зала
     * заменяют под тем же именем, и без версии браузер показывал старую.
     */
    private function versioned(string $path): string
    {
        $file = public_path($path);

        return asset($path).(is_file($file) ? '?v='.filemtime($file) : '');
    }
}
