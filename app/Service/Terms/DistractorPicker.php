<?php

namespace App\Service\Terms;

use Closure;

/**
 * Способ Б: подбор неверных вариантов к признаку. Чистая логика на массивах
 * — признаки из БД достаёт вызывающий код.
 *
 * Запрещено брать в дистракторы:
 *  - признаки самого термина;
 *  - признаки терминов, связанных с ним по линии parent (у родового понятия
 *    они верны и для видового: «содержит нормы права» — и про НПА, и про
 *    закон);
 *  - признаки из той же group, что верный вариант, и два дистрактора из
 *    одной group (дубли и признаки, верные сразу для нескольких терминов);
 *  - варианты с одинаковым текстом.
 *
 * Из оставшихся — сначала из терминов confusable_with, потом из того же
 * раздела кодификатора, потом из любых: чем ближе термин, тем правдоподобнее
 * дистрактор.
 *
 * Чтобы верный вариант не вычислялся без знаний, в одном квизе дистракторы
 * не повторяются от вопроса к вопросу ($avoidIds), а из confusable_with в
 * вопрос идёт не больше $maxConfusable. Без этого у термина с одним
 * «путаемым» соседом на три признака все вопросы квиза показывали бы одни и
 * те же три неверных варианта, и менялся бы только верный.
 */
class DistractorPicker
{
    private Closure $shuffle;

    /**
     * @param Closure|null $shuffle fn(array): array — подменяется в тестах
     */
    public function __construct(?Closure $shuffle = null)
    {
        $this->shuffle = $shuffle ?? function (array $items): array {
            shuffle($items);

            return $items;
        };
    }

    /**
     * Термины, связанные с данным по линии parent в любую сторону и через
     * любое число шагов (предки и потомки, но не соседи по родителю).
     *
     * @param array<int, int|null> $parentOf id термина => id родителя
     * @return int[]
     */
    public function relatedTermIds(array $parentOf, int $termId): array
    {
        $related = [];

        // seen защищает от зацикливания, если в данных parent замкнулся.
        $seen = [$termId => true];
        $current = $parentOf[$termId] ?? null;
        while ($current !== null && !isset($seen[$current])) {
            $seen[$current] = true;
            $related[] = (int) $current;
            $current = $parentOf[$current] ?? null;
        }

        $childrenOf = [];
        foreach ($parentOf as $child => $parent) {
            if ($parent !== null) {
                $childrenOf[$parent][] = $child;
            }
        }

        $stack = $childrenOf[$termId] ?? [];
        while ($stack) {
            $child = array_pop($stack);
            if (isset($seen[$child])) {
                continue;
            }
            $seen[$child] = true;
            $related[] = (int) $child;
            foreach ($childrenOf[$child] ?? [] as $grandchild) {
                $stack[] = $grandchild;
            }
        }

        return $related;
    }

    /**
     * @param array{term_id: int, section: int, group: ?string, text: string} $correct верный признак
     * @param int[] $relatedTermIds см. relatedTermIds()
     * @param int[] $confusableTermIds
     * @param array<int, array{id: int, term_id: int, section: int, group: ?string, text: string}> $pool все признаки базы
     * @param int[] $avoidIds признаки, уже показанные дистракторами в этом квизе
     * @param int|null $maxConfusable сколько дистракторов брать из confusable_with; null — без ограничения
     * @return array<int, array> до $count признаков из $pool; меньше — только если в базе не нашлось
     */
    public function pick(
        array $correct,
        array $relatedTermIds,
        array $confusableTermIds,
        array $pool,
        int $count = 3,
        array $avoidIds = [],
        ?int $maxConfusable = null
    ): array {
        $banned = array_flip($relatedTermIds);
        $banned[$correct['term_id']] = true;
        $confusable = array_flip($confusableTermIds);

        $tiers = [[], [], []];
        foreach ($pool as $feature) {
            if (isset($banned[$feature['term_id']])) {
                continue;
            }
            if (isset($confusable[$feature['term_id']])) {
                $tiers[0][] = $feature;
            } elseif ($feature['section'] === $correct['section']) {
                $tiers[1][] = $feature;
            } else {
                $tiers[2][] = $feature;
            }
        }
        $tiers = array_map($this->shuffle, $tiers);

        $usedGroups = $correct['group'] !== null ? [$correct['group'] => true] : [];
        $usedTexts = [$this->textKey($correct['text']) => true];
        $pickedIds = [];
        $picked = [];
        $fromConfusable = 0;

        // Проходы от строгого к мягкому: сначала соблюдаем и «не повторять»,
        // и потолок на confusable; если вариантов не хватило — снимаем
        // потолок, затем разрешаем повторы. Запреты по термину, группе и
        // тексту не снимаются никогда.
        $avoid = array_flip($avoidIds);
        foreach ([[true, true], [true, false], [false, false]] as [$skipAvoided, $capConfusable]) {
            foreach ($tiers as $tierIndex => $tier) {
                foreach ($tier as $feature) {
                    if (count($picked) >= $count) {
                        break 3;
                    }
                    if (isset($pickedIds[$feature['id']])) {
                        continue;
                    }
                    if ($skipAvoided && isset($avoid[$feature['id']])) {
                        continue;
                    }
                    if ($capConfusable && $tierIndex === 0 && $maxConfusable !== null && $fromConfusable >= $maxConfusable) {
                        continue;
                    }

                    $text = $this->textKey($feature['text']);
                    if (isset($usedTexts[$text])) {
                        continue;
                    }
                    if ($feature['group'] !== null && isset($usedGroups[$feature['group']])) {
                        continue;
                    }

                    $usedTexts[$text] = true;
                    if ($feature['group'] !== null) {
                        $usedGroups[$feature['group']] = true;
                    }
                    $pickedIds[$feature['id']] = true;
                    $picked[] = $feature;
                    if ($tierIndex === 0) {
                        $fromConfusable++;
                    }
                }
            }
        }

        return $picked;
    }

    /**
     * Убирает из пула признаки, которые верны и для самого термина: стоящие
     * в одной group с ЛЮБЫМ его признаком или совпадающие с ним по тексту.
     * Проверять только признак текущего вопроса мало: «общая культура и
     * язык» у нации — дубль признака этноса, и как дистрактор к другому
     * признаку этноса он тоже был бы верным утверждением про этнос.
     *
     * @param array<int, array{group: ?string, text: string}> $ownFeatures все признаки термина
     */
    public function foreignPool(array $ownFeatures, array $pool): array
    {
        $groups = [];
        $texts = [];
        foreach ($ownFeatures as $feature) {
            if ($feature['group'] !== null) {
                $groups[$feature['group']] = true;
            }
            $texts[$this->textKey($feature['text'])] = true;
        }

        return array_values(array_filter($pool, function (array $feature) use ($groups, $texts) {
            return !($feature['group'] !== null && isset($groups[$feature['group']]))
                && !isset($texts[$this->textKey($feature['text'])]);
        }));
    }

    private function textKey(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text)));
    }
}
