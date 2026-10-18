<?php

namespace Tests\Unit\Service\Terms;

use App\Service\Terms\DistractorPicker;
use PHPUnit\Framework\TestCase;

class DistractorPickerTest extends TestCase
{
    /** Без перемешивания — порядок кандидатов в тесте предсказуем. */
    private function picker(): DistractorPicker
    {
        return new DistractorPicker(fn (array $items) => $items);
    }

    private function feature(int $id, int $termId, string $text, ?string $group = null, int $section = 5): array
    {
        return ['id' => $id, 'term_id' => $termId, 'section' => $section, 'group' => $group, 'text' => $text];
    }

    /** @test */
    public function related_terms_are_ancestors_and_descendants_but_not_siblings()
    {
        // 1 НПА ← 2 закон ← 3 конституция; 4 подзаконный акт — сосед закона
        $parentOf = [1 => null, 2 => 1, 3 => 2, 4 => 1, 5 => null];
        $picker = $this->picker();

        $related = $picker->relatedTermIds($parentOf, 2);
        sort($related);
        $this->assertSame([1, 3], $related);

        $related = $picker->relatedTermIds($parentOf, 1);
        sort($related);
        $this->assertSame([2, 3, 4], $related);

        $this->assertSame([], $picker->relatedTermIds($parentOf, 5));
    }

    /** @test */
    public function a_parent_loop_in_the_data_does_not_hang()
    {
        $related = $this->picker()->relatedTermIds([1 => 2, 2 => 1], 1);

        $this->assertSame([2], $related);
    }

    /** @test */
    public function features_of_the_same_term_and_of_related_terms_are_never_used()
    {
        $correct = $this->feature(1, 2, 'регулирует наиболее значимые общественные отношения');
        $pool = [
            $this->feature(2, 2, 'принимается в особом порядке'),  // тот же термин
            $this->feature(3, 1, 'содержит нормы права'),           // родитель
            $this->feature(4, 4, 'издаётся органами исполнительной власти'),
            $this->feature(5, 9, 'влечет судимость'),
        ];

        $picked = $this->picker()->pick($correct, [1], [1, 4], $pool);

        $this->assertSame([4, 5], array_column($picked, 'id'));
    }

    /** @test */
    public function confusable_terms_come_first_then_the_same_section_then_the_rest()
    {
        $correct = $this->feature(1, 10, 'верный', null, 2);
        $pool = [
            $this->feature(2, 20, 'другой раздел', null, 4),
            $this->feature(3, 30, 'тот же раздел', null, 2),
            $this->feature(4, 40, 'путают', null, 4),
        ];

        $picked = $this->picker()->pick($correct, [], [40], $pool);

        $this->assertSame([4, 3, 2], array_column($picked, 'id'));
    }

    /** @test */
    public function the_group_of_the_correct_feature_is_excluded()
    {
        $correct = $this->feature(1, 10, 'общая культура и язык', 'g7');
        $pool = [
            $this->feature(2, 20, 'общая культура и язык', 'g7'),
            $this->feature(3, 30, 'единство языка и культуры', 'g7'),
            $this->feature(4, 40, 'наличие территории'),
        ];

        $picked = $this->picker()->pick($correct, [], [], $pool);

        $this->assertSame([4], array_column($picked, 'id'));
    }

    /** @test */
    public function two_distractors_never_share_a_group_or_a_text()
    {
        $correct = $this->feature(1, 10, 'верный');
        $pool = [
            $this->feature(2, 20, 'устанавливается государством', 'g18'),
            $this->feature(3, 30, 'санкционируется государством', 'g18'),
            $this->feature(4, 40, 'Составляется  в письменной форме'),
            $this->feature(5, 50, 'составляется в письменной форме'),
            $this->feature(6, 60, 'носит добровольный характер'),
        ];

        $picked = $this->picker()->pick($correct, [], [], $pool);

        $this->assertSame([2, 4, 6], array_column($picked, 'id'));
    }

    /** @test */
    public function distractors_shown_earlier_in_the_quiz_are_not_repeated()
    {
        $correct = $this->feature(1, 10, 'верный');
        $pool = [];
        for ($i = 2; $i < 12; $i++) {
            $pool[] = $this->feature($i, $i * 10, "признак {$i}");
        }

        $first = array_column($this->picker()->pick($correct, [], [], $pool), 'id');
        $second = array_column($this->picker()->pick($correct, [], [], $pool, 3, $first), 'id');

        $this->assertSame([2, 3, 4], $first);
        $this->assertSame([5, 6, 7], $second);
    }

    /** @test */
    public function repeats_come_back_only_when_nothing_else_is_left()
    {
        $correct = $this->feature(1, 10, 'верный');
        $pool = [
            $this->feature(2, 20, 'первый'),
            $this->feature(3, 30, 'второй'),
            $this->feature(4, 40, 'третий'),
            $this->feature(5, 50, 'четвёртый'),
        ];

        $picked = array_column($this->picker()->pick($correct, [], [], $pool, 3, [2, 3, 4]), 'id');

        // Сначала непоказанный, остальное добирается из уже показанных.
        $this->assertSame([5, 2, 3], $picked);
    }

    /** @test */
    public function only_some_distractors_come_from_confusable_terms()
    {
        $correct = $this->feature(1, 10, 'верный', null, 2);
        $pool = [
            $this->feature(2, 40, 'путают 1', null, 2),
            $this->feature(3, 40, 'путают 2', null, 2),
            $this->feature(4, 40, 'путают 3', null, 2),
            $this->feature(5, 50, 'тот же раздел', null, 2),
        ];

        $capped = array_column($this->picker()->pick($correct, [], [40], $pool, 3, [], 2), 'id');
        $this->assertSame([2, 3, 5], $capped);

        // Потолок снимается, если иначе вариантов не хватает.
        $short = array_column($this->picker()->pick($correct, [], [40], array_slice($pool, 0, 3), 3, [], 2), 'id');
        $this->assertSame([2, 3, 4], $short);
    }

    /** @test */
    public function features_that_are_also_true_for_the_term_leave_the_pool()
    {
        // У этноса два признака; «общая культура и язык» в группе g7 с признаком нации.
        $own = [
            $this->feature(1, 10, 'общая культура и язык', 'g7'),
            $this->feature(2, 10, 'общность обычаев и традиций'),
        ];
        $pool = array_merge($own, [
            $this->feature(3, 20, 'единство языка и культуры', 'g7'),   // та же группа, что у чужого признака этноса
            $this->feature(4, 30, 'Общность обычаев  и традиций'),       // тот же текст
            $this->feature(5, 40, 'общность территории'),
            $this->feature(6, 50, 'наличие вины', 'g17'),                // чужая группа — можно
        ]);

        $foreign = $this->picker()->foreignPool($own, $pool);

        $this->assertSame([5, 6], array_column($foreign, 'id'));
    }

    /** @test */
    public function it_returns_no_more_than_asked()
    {
        $correct = $this->feature(1, 10, 'верный');
        $pool = [];
        for ($i = 2; $i < 12; $i++) {
            $pool[] = $this->feature($i, $i * 10, "признак {$i}");
        }

        $this->assertCount(3, $this->picker()->pick($correct, [], [], $pool));
        $this->assertCount(5, $this->picker()->pick($correct, [], [], $pool, 5));
    }
}
