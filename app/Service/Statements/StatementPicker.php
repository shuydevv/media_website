<?php

namespace App\Service\Statements;

/**
 * Какие карточки попадут в сессию и в каком порядке. Чистая логика без БД.
 *
 * Кандидат — ['id' => int, 'is_true' => bool, 'task' => ?int] (task —
 * задание-источник). Порядок отбора:
 *  1) суждения на повтор (уже отсортированы вызывающим: самые давние первыми);
 *  2) новые;
 *  3) уже решённые, начиная с самых давних — только если новых не хватило.
 * Новые и решённые добираются так, чтобы верных и неверных было поровну
 * (иначе можно жать «Верно» наугад). Две карточки из одного задания подряд
 * не идут, если есть чем их разделить.
 */
class StatementPicker
{
    /** @var callable(array): array перемешивание — подменяется в тестах */
    private $shuffle;

    public function __construct(?callable $shuffle = null)
    {
        $this->shuffle = $shuffle ?? function (array $items): array {
            shuffle($items);

            return $items;
        };
    }

    /**
     * @param  array<int, array>  $due
     * @param  array<int, array>  $unseen
     * @param  array<int, array>  $seen
     * @return int[] id карточек по порядку
     */
    public function pick(array $due, array $unseen, array $seen, int $size): array
    {
        $chosen = array_slice(array_values($due), 0, $size);

        foreach ([($this->shuffle)(array_values($unseen)), array_values($seen)] as $pool) {
            $chosen = $this->fillBalanced($chosen, $pool, $size);
        }

        return array_column($this->spread(($this->shuffle)($chosen)), 'id');
    }

    private function fillBalanced(array $chosen, array $pool, int $size): array
    {
        $byTruth = [
            1 => array_values(array_filter($pool, fn ($c) => $c['is_true'])),
            0 => array_values(array_filter($pool, fn ($c) => ! $c['is_true'])),
        ];

        while (count($chosen) < $size && ($byTruth[1] || $byTruth[0])) {
            $trues = count(array_filter($chosen, fn ($c) => $c['is_true']));
            $want = $trues * 2 <= count($chosen) ? 1 : 0;
            if (! $byTruth[$want]) {
                $want = 1 - $want;
            }
            $chosen[] = array_shift($byTruth[$want]);
        }

        return $chosen;
    }

    /**
     * Разводит карточки из одного задания: каждый раз берём задание, у
     * которого осталось больше всего карточек, но не то, что было только что
     * (иначе в хвосте остаются одни карточки одного задания). При равенстве —
     * то, что раньше в перемешанном порядке, так что порядок остаётся
     * случайным, когда задания все разные.
     */
    private function spread(array $items): array
    {
        $groups = [];
        foreach ($items as $i => $item) {
            $groups[$item['task'] === null ? 'i'.$i : 't'.$item['task']][] = $item;
        }

        $result = [];
        $last = null;
        while ($groups) {
            $best = null;
            foreach ($groups as $key => $group) {
                if ($key === $last && count($groups) > 1) {
                    continue;
                }
                if ($best === null || count($group) > count($groups[$best])) {
                    $best = $key;
                }
            }
            $result[] = array_shift($groups[$best]);
            if (! $groups[$best]) {
                unset($groups[$best]);
            }
            $last = $best;
        }

        return $result;
    }
}
