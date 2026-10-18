<?php

namespace App\Service\Terms;

/**
 * Способ А: сравнение введённого названия термина с правильным и раскладка
 * названия по квадратикам. Чистая логика без БД.
 */
class AnswerMatcher
{
    public function __construct(private int $typoMinLength = 6)
    {
    }

    public static function fromConfig(): self
    {
        return new self((int) config('terms.typo_min_length'));
    }

    /**
     * Буквы названия для квадратиков: пробелы, дефисы и прочие знаки
     * пропускаются, регистр и «ё» сохраняются — это то, что видит ученик.
     *
     * @return string[]
     */
    public function cells(string $title): array
    {
        preg_match_all('/[\p{L}\p{N}]/u', $title, $m);

        return $m[0];
    }

    /**
     * Форма для сравнения: без регистра, «ё» = «е», только буквы и цифры.
     */
    public function normalize(string $value): string
    {
        $value = str_replace('ё', 'е', mb_strtolower($value));

        return implode('', $this->cells($value));
    }

    /**
     * Верен ли ответ: совпал с любым из названий (основное или «другие
     * названия»), либо отличается от него одной буквой — заменой, пропуском
     * или лишней. Опечатка прощается только в названиях от typoMinLength
     * букв: в коротких одна буква — это уже другое слово («труд» / «трус»).
     *
     * @param string[] $names
     */
    public function matches(string $input, array $names): bool
    {
        $input = $this->normalize($input);
        if ($input === '') {
            return false;
        }

        foreach ($names as $name) {
            $name = $this->normalize($name);
            if ($name === '') {
                continue;
            }
            if ($input === $name) {
                return true;
            }
            if (mb_strlen($name) >= $this->typoMinLength && $this->withinOneEdit($input, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ответ неверный, но «почти»: до одного из названий не хватает двух-трёх
     * букв, и это небольшая доля слова. Одна буква сюда не попадает — она
     * прощается и засчитывается как верный ответ (см. matches()).
     *
     * @param string[] $names
     */
    public function isClose(string $input, array $names, int $maxEdits = 3, float $maxShare = 0.25): bool
    {
        $input = $this->normalize($input);
        if ($input === '') {
            return false;
        }

        foreach ($names as $name) {
            $name = $this->normalize($name);
            $length = mb_strlen($name);
            if ($length === 0) {
                continue;
            }

            $edits = $this->distance($input, $name);
            if ($edits <= $maxEdits && $edits <= $length * $maxShare) {
                return true;
            }
        }

        return false;
    }

    /**
     * Расстояние Левенштейна по символам (встроенный levenshtein() считает
     * байты, а кириллица в UTF-8 двухбайтная).
     */
    public function distance(string $a, string $b): int
    {
        $a = mb_str_split($a);
        $b = mb_str_split($b);
        $row = range(0, count($b));

        foreach ($a as $i => $charA) {
            $next = [$i + 1];
            foreach ($b as $j => $charB) {
                $next[] = min(
                    $row[$j + 1] + 1,                       // удалить
                    $next[$j] + 1,                          // вставить
                    $row[$j] + ($charA === $charB ? 0 : 1)  // заменить
                );
            }
            $row = $next;
        }

        return $row[count($b)];
    }

    /**
     * Расстояние Левенштейна <= 1. Встроенный levenshtein() считает байты,
     * а кириллица в UTF-8 двухбайтная, поэтому сравниваем посимвольно сами.
     */
    private function withinOneEdit(string $a, string $b): bool
    {
        $a = mb_str_split($a);
        $b = mb_str_split($b);
        $la = count($a);
        $lb = count($b);

        if (abs($la - $lb) > 1) {
            return false;
        }

        // Общий префикс, затем общий суффикс: при одной правке между ними
        // остаётся не больше одного символа в каждой строке.
        $start = 0;
        while ($start < $la && $start < $lb && $a[$start] === $b[$start]) {
            $start++;
        }

        $endA = $la - 1;
        $endB = $lb - 1;
        while ($endA >= $start && $endB >= $start && $a[$endA] === $b[$endB]) {
            $endA--;
            $endB--;
        }

        return ($endA - $start + 1) <= 1 && ($endB - $start + 1) <= 1;
    }
}
