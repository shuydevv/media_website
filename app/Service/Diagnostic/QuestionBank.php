<?php

namespace App\Service\Diagnostic;

use RuntimeException;

/**
 * Банк вопросов диагностики — JSON (config diagnostic.bank_path), правит
 * автор. Формат — ТЗ, раздел 10. Код БД не трогает: банк читается из файла,
 * в лог ответов пишется id вопроса и хеш содержимого (hash()), чтобы правка
 * текста не смешала статистику старой и новой версии.
 *
 * validate() — то же, что команда diagnostic:validate: без ошибок банк
 * гарантированно проходится (на каждом уровне каждого блока хватает
 * вопросов на худший путь, ключи ответов указывают на существующие варианты).
 */
final class QuestionBank
{
    /** Сколько вопросов одного уровня может понадобиться за прохождение блока (BlockRun). */
    public const MIN_PER_LEVEL = 2;

    /**
     * Оформления механик (поле skin; без skin — обычное). Проверка ответа от
     * оформления не зависит — меняется только подача (ТЗ, раздел 6).
     */
    public const SKINS = [
        'swipe' => ['chat'],                       // «Чат класса»: утверждение — сообщение персонажа (from)
        'single' => ['case', 'odd', 'who'],         // мини-кейс, «Найди лишнее», «Кто прав?»
        'multi' => ['fragments', 'debate'],         // «Ты эксперт ЕГЭ», «Помоги выиграть спор»
        'buckets' => ['cases'],                     // «Разрули ситуации»: карточки — сообщения людей
        'fix' => [],
    ];

    /** Механики сложного уровня — где угадать почти нельзя. */
    public const HARD_MECHANICS = ['multi', 'fix'];

    /** @param  array<string, array>  $questions  id → вопрос */
    public function __construct(private readonly array $questions)
    {
    }

    public static function fromConfig(): self
    {
        static $cache = [];

        $path = config('diagnostic.bank_path');
        $key = $path.'@'.(is_file($path) ? filemtime($path) : 0);

        return $cache[$key] ??= self::fromFile($path);
    }

    public static function fromFile(string $path): self
    {
        $raw = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (! is_array($raw) || ! isset($raw['questions']) || ! is_array($raw['questions'])) {
            throw new RuntimeException("Банк диагностики не прочитан: {$path}");
        }

        $byId = [];
        foreach ($raw['questions'] as $q) {
            if (is_array($q) && isset($q['id'])) {
                $byId[(string) $q['id']] = $q;
            }
        }

        return new self($byId);
    }

    public function get(string $id): ?array
    {
        return $this->questions[$id] ?? null;
    }

    /** @return array<string, array> id → вопрос */
    public function all(): array
    {
        return $this->questions;
    }

    public static function hash(array $q): string
    {
        return substr(sha1(json_encode($q, JSON_UNESCAPED_UNICODE)), 0, 12);
    }

    /**
     * Случайный вопрос уровня: не из уже заданных в этом прохождении
     * ($used), по возможности — из тех, что человек ещё не видел ($seen,
     * прошлые прохождения).
     *
     * @param  string[]  $used
     * @param  string[]  $seen
     */
    public function pick(string $block, int $level, array $used, array $seen = []): ?array
    {
        $pool = array_values(array_filter(
            $this->questions,
            fn ($q) => $q['block'] === $block && (int) $q['level'] === $level && ! in_array($q['id'], $used, true),
        ));
        if ($pool === []) {
            return null;
        }

        // Первое прохождение — важнее разнообразие: реже всего встречавшаяся в
        // этом прохождении механика с оформлением, потом сама механика, потом —
        // не та же, что в прошлом вопросе. Повторное ($seen не пуст) — сначала
        // то, чего человек ещё не видел, а уже среди них — разнообразие.
        $kinds = array_map(fn ($id) => $this->kind($this->questions[$id] ?? []), $used);
        $mechanics = array_map(fn ($k) => strtok($k, ':'), $kinds);
        $last = end($kinds) ?: null;
        $retake = $seen !== [];
        $score = function ($q) use ($kinds, $mechanics, $last, $seen, $retake) {
            $variety = [
                count(array_keys($kinds, $this->kind($q), true)),
                count(array_keys($mechanics, $q['mechanic'] ?? '', true)),
                (int) ($this->kind($q) === $last),
            ];
            $unseen = (int) in_array($q['id'], $seen, true);

            return $retake ? [$unseen, ...$variety] : [...$variety, $unseen];
        };

        $best = null;
        $from = [];
        foreach ($pool as $q) {
            $s = $score($q);
            if ($best === null || $s < $best) {
                [$best, $from] = [$s, [$q]];
            } elseif ($s === $best) {
                $from[] = $q;
            }
        }

        return $from[array_rand($from)];
    }

    /** Механика с оформлением — «вид» вопроса для разнообразия: «single:who». */
    private function kind(array $q): string
    {
        return ($q['mechanic'] ?? '').':'.($q['skin'] ?? '');
    }

    /**
     * @param  string[]  $blocks  ключи блоков из конфига
     * @param  string[]|null  $characters  ключи персонажей (config diagnostic.characters); null — не проверять
     * @return string[] ошибки; пусто — банк годится
     */
    public function validate(array $blocks, ?array $characters = null): array
    {
        $errors = [];
        $count = [];

        foreach ($this->questions as $id => $q) {
            $at = "«{$id}»";
            foreach (['block', 'level', 'mechanic', 'prompt', 'explain_correct', 'explain_wrong'] as $field) {
                if (! isset($q[$field]) || $q[$field] === '') {
                    $errors[] = "{$at}: нет поля {$field}";
                }
            }
            if (isset($q['block']) && ! in_array($q['block'], $blocks, true)) {
                $errors[] = "{$at}: неизвестный блок {$q['block']}";
            }
            $level = (int) ($q['level'] ?? 0);
            if ($level < BlockRun::EASY || $level > BlockRun::HARD) {
                $errors[] = "{$at}: уровень должен быть 1, 2 или 3";
            }
            $mechanic = $q['mechanic'] ?? '';
            if ($mechanic === 'chips') {
                $errors[] = "{$at}: пропуски (chips) больше не используем — угадываются; замените на fix («Найди и исправь»)";

                continue;
            }
            if (! in_array($mechanic, AnswerChecker::MECHANICS, true)) {
                $errors[] = "{$at}: неизвестная механика {$mechanic}";

                continue;
            }

            // Угадать свайп — 50%: только на лёгких. На сложных — только
            // выбор множества, где угадать почти нельзя (ТЗ, раздел 6).
            if ($mechanic === 'swipe' && $level !== BlockRun::EASY) {
                $errors[] = "{$at}: свайп — только на лёгком уровне";
            }
            if ($level === BlockRun::HARD && ! in_array($mechanic, self::HARD_MECHANICS, true)) {
                $errors[] = "{$at}: на сложном уровне — только multi или fix";
            }
            $skin = $q['skin'] ?? null;
            if ($skin !== null && ! in_array($skin, self::SKINS[$mechanic], true)) {
                $errors[] = "{$at}: у {$mechanic} нет оформления {$skin}";
            }
            if ($characters !== null) {
                foreach ($this->characterRefs($q) as $ref) {
                    if (! in_array($ref, $characters, true)) {
                        $errors[] = "{$at}: нет персонажа {$ref} (config diagnostic.characters)";
                    }
                }
            }
            if (in_array($skin, ['chat', 'debate', 'fragments'], true) && ! isset($q['from'])) {
                $errors[] = "{$at}: у оформления {$skin} нужен from — чьё это сообщение";
            }

            foreach ($this->mechanicErrors($q) as $error) {
                $errors[] = "{$at}: {$error}";
            }

            $count[$q['block'] ?? ''][$level] = ($count[$q['block'] ?? ''][$level] ?? 0) + 1;
        }

        foreach ($blocks as $block) {
            for ($level = BlockRun::EASY; $level <= BlockRun::HARD; $level++) {
                $have = $count[$block][$level] ?? 0;
                if ($have < self::MIN_PER_LEVEL) {
                    $errors[] = "Блок {$block}, уровень {$level}: вопросов {$have}, нужно хотя бы ".self::MIN_PER_LEVEL;
                }
            }
        }

        return $errors;
    }

    /** @return string[] */
    private function mechanicErrors(array $q): array
    {
        $ids = fn (string $field) => array_map(fn ($o) => (string) ($o['id'] ?? ''), (array) ($q[$field] ?? []));

        switch ($q['mechanic']) {
            case 'swipe':
                return is_bool($q['answer'] ?? null) ? [] : ['answer должен быть true или false'];

            case 'single':
                if (($q['skin'] ?? null) === 'who' && array_filter((array) ($q['speakers'] ?? []), fn ($sp) => ! isset($sp['who']) && ! isset($sp['name']))) {
                    return ['у каждого в speakers нужен who (персонаж) или name'];
                }
                $options = ($q['skin'] ?? null) === 'who' ? $ids('speakers') : $ids('options');

                return in_array((string) ($q['correct'] ?? ''), $options, true) && count($options) >= 2
                    ? [] : ['correct должен быть id одного из вариантов (у «Кто прав?» — из speakers)'];

            case 'multi':
                $options = $ids('options');
                $correct = array_map('strval', (array) ($q['correct'] ?? []));
                $errors = [];
                if ($correct === [] || array_diff($correct, $options) !== []) {
                    $errors[] = 'correct — непустой список id из options';
                }
                if (isset($q['pick']) && (int) $q['pick'] !== count($correct)) {
                    $errors[] = 'pick должен совпадать с числом верных';
                }

                return $errors;

            case 'buckets':
                $buckets = $ids('buckets');
                $cards = $ids('cards');
                $mapping = (array) ($q['mapping'] ?? []);
                $keys = array_map('strval', array_keys($mapping));
                sort($keys);
                sort($cards);

                return $keys === $cards && array_diff(array_map('strval', $mapping), $buckets) === []
                    ? [] : ['mapping должен разложить каждую карточку в существующую корзину'];

            case 'fix':
                preg_match_all('/\{\{(\w+)\}\}/u', (string) ($q['text'] ?? ''), $m);
                $places = $m[1];
                $spots = (array) ($q['spots'] ?? []);
                $spotIds = $ids('spots');
                sort($places);
                sort($spotIds);
                if ($places === [] || $places !== $spotIds) {
                    return ['в text места {{id}}, в spots — по одному на каждое место'];
                }
                $errors = [];
                foreach ($spots as $spot) {
                    $options = array_map('strval', (array) ($spot['options'] ?? []));
                    if (count($options) < 2 || ! in_array((string) ($spot['shown'] ?? ''), $options, true) || ! in_array((string) ($spot['correct'] ?? ''), $options, true)) {
                        $errors[] = "место {$spot['id']}: shown и correct должны быть среди options (хотя бы 2 варианта)";
                    }
                }
                // Без ошибки в тексте искать нечего; без верных мест — ошибки видны сразу.
                $wrong = count(array_filter($spots, fn ($sp) => ($sp['shown'] ?? null) !== ($sp['correct'] ?? null)));
                if ($wrong === 0) {
                    $errors[] = 'хотя бы одно место должно быть с ошибкой (shown ≠ correct)';
                }
                if ($wrong === count($spots)) {
                    $errors[] = 'нужно хотя бы одно верное место (shown = correct) — иначе ошибки не надо искать';
                }

                return $errors;
        }

        return [];
    }

    /** @return string[] ключи персонажей, на которых ссылается вопрос */
    private function characterRefs(array $q): array
    {
        $refs = array_filter([$q['from'] ?? null, $q['explain_by'] ?? null]);
        foreach ((array) ($q['speakers'] ?? []) as $sp) {
            if (isset($sp['who'])) {
                $refs[] = $sp['who'];
            }
        }

        return array_values(array_unique(array_map('strval', $refs)));
    }
}
