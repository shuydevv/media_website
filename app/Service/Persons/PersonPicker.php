<?php

namespace App\Service\Persons;

/**
 * Подбор неверных вариантов в тренажёре личностей. Чистая логика без БД:
 * личность — массив {id, name, era, role, association, description, related: int[]}.
 *
 * Варианты должны быть похожими, но не спорными:
 *  - ближе всего — тот же период и та же роль (полководец к полководцу),
 *    потом тот же период, потом соседний; дальние периоды — в крайнем
 *    случае. Иначе Терешкова рядом с Тохтамышем угадывается без знаний;
 *  - не годятся люди с той же ассоциацией (Минин и Пожарский, Адашев и
 *    Сильвестр): верных ответов стало бы два;
 *  - не годятся связанные (related, в обе стороны) — «церковный раскол»
 *    подходит и Аввакуму, и Никону;
 *  - не годятся те, о ком говорит текст другого («расстрелян Сталиным» среди
 *    вариантов к Сталину, «регентство за Ивана V» — к Ивану V);
 *  - тексты вариантов не повторяются.
 */
class PersonPicker
{
    // Слова в имени, которые не узнают человека.
    private const TITLE_WORDS = ['протопоп', 'митрополит', 'патриарх', 'хан', 'царевна', 'великий', 'князь', 'династия'];

    // Общие основы слов в ассоциациях: по ним два события не считаются одним.
    private const GENERIC_STEMS = [
        'войны', 'война', 'войну', 'войне', 'битва', 'битвы', 'битве', 'сраже', 'рефор', 'эпоха', 'эпохи',
        'смутн', 'время', 'русск', 'росси', 'совет', 'велик', 'отече', 'перва', 'перво', 'первы', 'перву',
        'перве', 'конец', 'начал', 'после', 'армии', 'армия', 'госуд', 'народ', 'движе', 'лидер', 'глава',
        'главы', 'проти', 'созда', 'освоб', 'захва', 'поход', 'восст', 'крест', 'полит', 'власт', 'перех',
        'город', 'перио', 'стран', 'новой', 'новая', 'новые', 'новый', 'лучши', 'лучша',
        'фронт', 'борьб', 'попыт', 'защит', 'побед', 'разгр', 'взяти', 'конст', 'мирны', 'союза', 'союз',
        'экспе', 'эконо', 'промы', 'строи', 'парти', 'кризи', 'годов',
    ];

    /**
     * $count неверных вариантов к $target из $pool.
     *
     * @param array $target личность
     * @param array[] $pool все личности (с самой target — она отсеется)
     * @param int[] $avoid id, которые уже показаны или не годятся по другой причине
     * @return array[] выбранные личности
     */
    public function pick(array $target, array $pool, int $count, array $avoid = []): array
    {
        $avoid = array_flip($avoid);
        $tiers = [];
        foreach ($pool as $candidate) {
            if ((int) $candidate['id'] === (int) $target['id'] || isset($avoid[(int) $candidate['id']])) {
                continue;
            }
            if ($this->conflicts($target, $candidate)) {
                continue;
            }
            $tiers[$this->distance($target, $candidate)][] = $candidate;
        }
        ksort($tiers);

        $picked = [];
        $texts = [$this->norm($target['association'])];
        foreach ($tiers as $tier) {
            shuffle($tier);
            foreach ($tier as $candidate) {
                if (count($picked) >= $count) {
                    break 2;
                }
                $text = $this->norm($candidate['association']);
                if (in_array($text, $texts, true) || $this->conflictsWithAny($candidate, $picked)) {
                    continue;
                }
                $picked[] = $candidate;
                $texts[] = $text;
            }
        }

        return $picked;
    }

    /**
     * Набор для «соответствия»: $size личностей из $ids (предпочтительно —
     * разные и не спорные между собой), к ним $extra лишних.
     *
     * @param int[] $ids кандидаты в строки (например, те, кого повторяли)
     * @param int[] $fallback чем добрать строки, если из $ids не набралось
     *   (например, все изученные): в одном наборе не бывает спорных пар
     * @return array{rows: int[], options: int[]}|null null — не набралось
     */
    public function matching(array $ids, array $pool, int $size = 4, int $extra = 2, array $fallback = []): ?array
    {
        $byId = [];
        foreach ($pool as $person) {
            $byId[(int) $person['id']] = $person;
        }

        $first = array_values(array_filter(array_map(fn ($id) => $byId[(int) $id] ?? null, array_unique($ids))));
        $more = array_values(array_filter(array_map(fn ($id) => $byId[(int) $id] ?? null, array_diff(array_unique($fallback), $ids))));
        shuffle($first);
        shuffle($more);
        $candidates = array_merge($first, $more);

        $rows = [];
        $texts = [];
        foreach ($candidates as $person) {
            $text = $this->norm($person['association']);
            if (in_array($text, $texts, true) || $this->conflictsWithAny($person, $rows)) {
                continue;
            }
            $rows[] = $person;
            $texts[] = $text;
            if (count($rows) === $size) {
                break;
            }
        }
        if (count($rows) < $size) {
            return null;
        }

        // Лишние — похожие на кого-то из строк и не спорные ни с одной строкой.
        $rowIds = array_map(fn ($p) => (int) $p['id'], $rows);
        $safe = array_values(array_filter($pool, fn ($p) => !in_array((int) $p['id'], $rowIds, true)
            && !$this->conflictsWithAny($p, $rows)));
        $extras = [];
        foreach ($rows as $row) {
            if (count($extras) >= $extra) {
                break;
            }
            $avoid = array_merge($rowIds, array_map(fn ($p) => (int) $p['id'], $extras));
            $extras = array_merge($extras, $this->pick($row, $safe, 1, $avoid));
        }
        if (count($extras) < $extra) {
            return null;
        }

        $options = array_merge($rowIds, array_map(fn ($p) => (int) $p['id'], $extras));
        shuffle($options);

        return ['rows' => $rowIds, 'options' => $options];
    }

    /**
     * Нельзя ставить рядом: одна ассоциация или одно событие в ассоциациях
     * («Бородинская битва» у Кутузова и у Багратиона — Кутузов там тоже
     * был), связаны, или один упомянут в тексте другого.
     */
    public function conflicts(array $a, array $b): bool
    {
        if ($this->norm($a['association']) === $this->norm($b['association'])) {
            return true;
        }
        if (array_intersect($this->topics($a['association']), $this->topics($b['association'])) !== []) {
            return true;
        }
        if (in_array((int) $b['id'], $a['related'] ?? [], true) || in_array((int) $a['id'], $b['related'] ?? [], true)) {
            return true;
        }

        return $this->mentions($a, $b) || $this->mentions($b, $a);
    }

    private function conflictsWithAny(array $person, array $others): bool
    {
        foreach ($others as $other) {
            if ($this->conflicts($person, $other)) {
                return true;
            }
        }

        return false;
    }

    /** Упомянут ли $who в описании или ассоциации $in. */
    private function mentions(array $in, array $who): bool
    {
        $text = ' '.$this->norm($in['description'].' '.$in['association']).' ';
        foreach ($this->nameKeys($who) as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Шаблоны, по которым человека узнают в чужом тексте. Римская цифра —
     * часть имени («Петра I» — не «Петра II»); у «Фамилия Имя» — только
     * фамилия (иначе любой «Иван» мешал бы всем Иванам). Основа слова — без
     * окончания, чтобы находились падежи.
     *
     * @return string[] регулярные выражения
     */
    public function nameKeys(array $person): array
    {
        $name = $this->norm(preg_replace('/\(.*?\)/u', '', $person['name']));
        $words = array_values(array_filter(preg_split('/[\s,]+/u', $name), fn ($w) => $w !== '' && $w !== 'и'));
        $numeral = null;
        foreach ($words as $w) {
            if (preg_match('/^[ivx]+$/u', $w)) {
                $numeral = $w;
            }
        }
        $words = array_values(array_filter($words, fn ($w) => !preg_match('/^[ivx]+$/u', $w)
            && !in_array($w, self::TITLE_WORDS, true) && mb_strlen($w) >= 3));

        if ($numeral !== null) {
            return $words ? ['/\b'.preg_quote($this->stem($words[0]), '/').'\w*\s+'.$numeral.'\b/u'] : [];
        }

        // «Фамилия Имя» у всех, кроме правителей и церковных имён с титулом.
        $isSurnameFirst = ($person['role'] ?? null) !== 'R' && count($words) >= 2
            && !preg_match('/^(протопоп|митрополит|патриарх|хан|царевна)\b/u', $name);
        if ($isSurnameFirst) {
            $words = [$words[0]];
        }

        return array_map(fn ($w) => '/\b'.preg_quote($this->stem($w), '/').'/u', $words);
    }

    /**
     * Ключевые слова события в ассоциации: годы и основы (5 букв) слов от
     * пяти букв, кроме общих («война», «реформы», «восстание», «Смутное время» —
     * они не делают два ответа спорными). Совпала основа — это одно событие:
     * «бород» (Бородино), «декаб», «перес» (Перестройка), «москв».
     *
     * @return string[]
     */
    private function topics(string $association): array
    {
        static $cache = [];
        if (isset($cache[$association])) {
            return $cache[$association];
        }

        preg_match_all('/\p{L}{5,}/u', $this->norm($association), $m);
        $stems = array_map(fn ($w) => mb_substr($w, 0, 5), $m[0]);
        // Один год — тоже одно событие: «Победа над Наполеоном (1812)» и
        // «Бородинская битва (1812)» в одном соответствии спорны.
        preg_match_all('/\b1\d{3}\b/u', $association, $years);
        $stems = array_merge($stems, $years[0]);

        return $cache[$association] = array_values(array_unique(array_diff($stems, self::GENERIC_STEMS)));
    }

    /** 0 — тот же период и роль, 1 — тот же период, 2/3 — соседний, дальше — по удалению. */
    private function distance(array $a, array $b): int
    {
        $eras = abs((int) $a['era'] - (int) $b['era']);
        $sameRole = ($a['role'] ?? null) === ($b['role'] ?? null);

        return $eras * 2 + ($sameRole ? 0 : 1);
    }

    private function stem(string $word): string
    {
        $length = mb_strlen($word);

        return $length <= 4 ? $word : mb_substr($word, 0, max(4, $length - 2));
    }

    private function norm(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace('ё', 'е', mb_strtolower($text))));
    }
}
