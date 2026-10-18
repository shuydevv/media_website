<?php

namespace Tests\Unit\Service\Terms;

use App\Service\Terms\AnswerMatcher;
use App\Service\Terms\TermImporter;
use App\Service\Terms\TermRelations;
use PHPUnit\Framework\TestCase;

/**
 * То, на чём держатся пояснения рыбы после ошибки: связь между понятиями,
 * «почти» в названии и заметки преподавателя к парам. Без БД.
 */
class TermRelationsTest extends TestCase
{
    // 1 НПА ← 2 закон ← 3 конституция; 4 подзаконный акт — сосед закона; 5 и 6 сами по себе
    private const PARENTS = [1 => null, 2 => 1, 3 => 2, 4 => 1, 5 => null, 6 => null];

    /** @test */
    public function the_other_concept_can_be_wider_or_narrower_through_any_number_of_steps()
    {
        $relations = new TermRelations();

        $this->assertSame(['kind' => 'wider'], $relations->between(self::PARENTS, 2, 1));
        $this->assertSame(['kind' => 'wider'], $relations->between(self::PARENTS, 3, 1));
        $this->assertSame(['kind' => 'narrower'], $relations->between(self::PARENTS, 1, 3));
    }

    /** @test */
    public function concepts_with_the_same_parent_are_siblings()
    {
        $this->assertSame(['kind' => 'sibling', 'parent' => 1], (new TermRelations())->between(self::PARENTS, 2, 4));
    }

    /** @test */
    public function a_confusable_mark_is_used_only_when_there_is_no_closer_relation()
    {
        $relations = new TermRelations();

        $this->assertSame(['kind' => 'confusable'], $relations->between(self::PARENTS, 5, 6, [6]));
        // Закон и НПА помечены путаемыми, но родство точнее.
        $this->assertSame(['kind' => 'wider'], $relations->between(self::PARENTS, 2, 1, [1]));
    }

    /** @test */
    public function unrelated_concepts_have_no_relation()
    {
        $relations = new TermRelations();

        $this->assertNull($relations->between(self::PARENTS, 5, 6));
        $this->assertNull($relations->between(self::PARENTS, 5, 5));
        // Два «корня» без родителя — не соседи.
        $this->assertNull($relations->between(self::PARENTS, 1, 5));
    }

    /** @test */
    public function a_parent_loop_in_the_data_does_not_hang()
    {
        $this->assertNull((new TermRelations())->between([1 => 2, 2 => 1, 3 => null], 3, 1));
    }

    /** @test */
    public function distance_counts_letters_not_bytes()
    {
        $matcher = new AnswerMatcher(6);

        $this->assertSame(0, $matcher->distance('труд', 'труд'));
        $this->assertSame(1, $matcher->distance('труд', 'трус'));
        $this->assertSame(2, $matcher->distance('манаполия', 'монополия'));
        $this->assertSame(4, $matcher->distance('труд', ''));
    }

    /** @test */
    public function almost_means_a_few_wrong_letters_in_a_long_enough_name()
    {
        $matcher = new AnswerMatcher(6);

        $this->assertTrue($matcher->isClose('Манаполия', ['Монополия']));
        $this->assertTrue($matcher->isClose('Нормотивно-провавой акт', ['Нормативно-правовой акт', 'НПА']));
        // В коротком слове две буквы — это уже другое слово.
        $this->assertFalse($matcher->isClose('Трап', ['Труд']));
        $this->assertFalse($matcher->isClose('чепуха', ['Монополия']));
        $this->assertFalse($matcher->isClose('', ['Монополия']));
    }

    /** @test */
    public function pair_notes_must_point_at_another_existing_term()
    {
        $term = fn (string $slug, array $notes = []) => [
            'id' => 1, 'slug' => $slug, 'title' => 'Термин', 'qualifier' => null, 'other_names' => [],
            'pair_notes' => $notes, 'image' => null, 'code' => '1.1', 'priority' => 1, 'parent' => null,
            'confusable_with' => [],
            'features' => [['id' => "{$slug}__f1", 'text' => 'признак', 'group' => null]],
        ];

        $this->assertSame([], TermImporter::validate([$term('vvp', ['vnp' => 'ВВП — по территории, ВНП — по производителю']), $term('vnp')]));

        $this->assertNotSame([], TermImporter::validate([$term('vvp', ['ghost' => 'заметка']), $term('vnp')]), 'несуществующий термин');
        $this->assertNotSame([], TermImporter::validate([$term('vvp', ['vvp' => 'заметка']), $term('vnp')]), 'сам на себя');
        $this->assertNotSame([], TermImporter::validate([$term('vvp', ['vnp' => '  ']), $term('vnp')]), 'пустая заметка');
        $this->assertNotSame([], TermImporter::validate([$term('vvp', ['просто строка']), $term('vnp')]), 'не объект');
    }
}
