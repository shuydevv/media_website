<?php

namespace App\Service\Terms;

/**
 * Как два понятия связаны между собой — по полям, которые в базе терминов
 * проставлены вручную (parent и confusable_with). Чистая логика без БД.
 *
 * Нужно рыбе, чтобы на ошибке сказать, откуда путаница: «оба — виды понятия
 * „Политический режим“», «„НПА“ — понятие шире».
 *
 * Здесь намеренно нет «отличий» между понятиями, выведенных из списков
 * признаков. Список в базе — это признаки, которые засчитываются в ответе на
 * задание 18, а не всё, что о понятии верно: у этноса общность территории
 * не записана, хотя она у него есть. Из отсутствия записи ничего утверждать
 * нельзя; настоящие отличия пишет человек (terms.pair_notes).
 */
class TermRelations
{
    public const WIDER = 'wider';           // другое понятие — родовое для текущего

    public const NARROWER = 'narrower';     // другое понятие — вид текущего

    public const SIBLING = 'sibling';       // у обоих один родитель

    public const CONFUSABLE = 'confusable'; // помечены как путаемые

    /**
     * @param array<int, int|null> $parentOf id термина => id родителя
     * @param int[] $confusableIds термины, помеченные путаемыми с $termId (в любую сторону)
     * @return array{kind: string, parent?: int}|null null — связи в базе нет
     */
    public function between(array $parentOf, int $termId, int $otherId, array $confusableIds = []): ?array
    {
        if ($termId === $otherId) {
            return null;
        }

        if ($this->isAncestor($parentOf, $otherId, $termId)) {
            return ['kind' => self::WIDER];
        }
        if ($this->isAncestor($parentOf, $termId, $otherId)) {
            return ['kind' => self::NARROWER];
        }

        $parent = $parentOf[$termId] ?? null;
        if ($parent !== null && ($parentOf[$otherId] ?? null) === $parent) {
            return ['kind' => self::SIBLING, 'parent' => (int) $parent];
        }

        if (in_array($otherId, $confusableIds, true)) {
            return ['kind' => self::CONFUSABLE];
        }

        return null;
    }

    private function isAncestor(array $parentOf, int $ancestorId, int $termId): bool
    {
        // seen защищает от зацикливания, если в данных parent замкнулся.
        $seen = [$termId => true];
        $current = $parentOf[$termId] ?? null;

        while ($current !== null && !isset($seen[$current])) {
            if ((int) $current === $ancestorId) {
                return true;
            }
            $seen[$current] = true;
            $current = $parentOf[$current] ?? null;
        }

        return false;
    }
}
