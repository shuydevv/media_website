<?php

namespace Tests\Unit\Service\Terms;

use App\Service\Terms\AnswerMatcher;
use PHPUnit\Framework\TestCase;

class AnswerMatcherTest extends TestCase
{
    private function matcher(): AnswerMatcher
    {
        return new AnswerMatcher(6);
    }

    /** @test */
    public function cells_skip_spaces_and_hyphens_but_keep_the_letters_as_written()
    {
        $this->assertSame(
            ['Н', 'о', 'р', 'м', 'а', 'т', 'и', 'в', 'н', 'о', 'п', 'р', 'а', 'в', 'о', 'в', 'о', 'й', 'а', 'к', 'т'],
            $this->matcher()->cells('Нормативно-правовой акт')
        );
        $this->assertSame(['М', 'о', 'л', 'о', 'д', 'ё', 'ж', 'ь'], $this->matcher()->cells('Молодёжь'));
    }

    /** @test */
    public function case_yo_spaces_and_hyphens_are_ignored()
    {
        $matcher = $this->matcher();

        $this->assertTrue($matcher->matches('молодежь', ['Молодёжь']));
        $this->assertTrue($matcher->matches('  РЫНОК  ТРУДА ', ['Рынок труда']));
        $this->assertTrue($matcher->matches('нормативноправовойакт', ['Нормативно-правовой акт']));
    }

    /** @test */
    public function other_names_count_as_correct()
    {
        $names = ['Валовой внутренний продукт', 'ВВП'];

        $this->assertTrue($this->matcher()->matches('ввп', $names));
        $this->assertFalse($this->matcher()->matches('внп', $names));
    }

    /** @test */
    public function one_typo_is_forgiven_in_long_names()
    {
        $matcher = $this->matcher();

        // замена, пропуск и лишняя буква
        $this->assertTrue($matcher->matches('Валовый внутренний продукт', ['Валовой внутренний продукт']));
        $this->assertTrue($matcher->matches('Монополя', ['Монополия']));
        $this->assertTrue($matcher->matches('Монопоолия', ['Монополия']));
    }

    /** @test */
    public function two_typos_are_an_error()
    {
        $this->assertFalse($this->matcher()->matches('Маноползя', ['Монополия']));
    }

    /** @test */
    public function a_typo_is_not_forgiven_in_short_names()
    {
        $matcher = $this->matcher();

        $this->assertFalse($matcher->matches('Трус', ['Труд']));
        $this->assertFalse($matcher->matches('Брат', ['Брак']));
        // Короткое «другое название» не делает длинный ответ терпимее к нему.
        $this->assertFalse($matcher->matches('ВНП', ['Валовой внутренний продукт', 'ВВП']));
    }

    /** @test */
    public function an_empty_answer_is_never_correct()
    {
        $this->assertFalse($this->matcher()->matches('  - ', ['Труд']));
    }
}
