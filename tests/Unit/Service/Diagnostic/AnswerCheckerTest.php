<?php

namespace Tests\Unit\Service\Diagnostic;

use App\Service\Diagnostic\AnswerChecker;
use PHPUnit\Framework\TestCase;

class AnswerCheckerTest extends TestCase
{
    public function test_swipe(): void
    {
        $q = ['mechanic' => 'swipe', 'answer' => false];

        $this->assertTrue(AnswerChecker::check($q, ['value' => false]));
        $this->assertFalse(AnswerChecker::check($q, ['value' => true]));
        $this->assertFalse(AnswerChecker::check($q, ['value' => 0]), 'только bool, не «похоже на false»');
        $this->assertFalse(AnswerChecker::check($q, []));
    }

    public function test_single(): void
    {
        $q = ['mechanic' => 'single', 'correct' => 'b'];

        $this->assertTrue(AnswerChecker::check($q, ['id' => 'b']));
        $this->assertFalse(AnswerChecker::check($q, ['id' => 'a']));
    }

    public function test_multi_needs_exact_set(): void
    {
        $q = ['mechanic' => 'multi', 'correct' => ['a', 'c']];

        $this->assertTrue(AnswerChecker::check($q, ['ids' => ['c', 'a']]));
        $this->assertFalse(AnswerChecker::check($q, ['ids' => ['a']]), 'не все верные');
        $this->assertFalse(AnswerChecker::check($q, ['ids' => ['a', 'b', 'c']]), 'лишний');
    }

    public function test_buckets_need_all_cards_right(): void
    {
        $q = ['mechanic' => 'buckets', 'mapping' => ['c1' => 'x', 'c2' => 'y']];

        $this->assertTrue(AnswerChecker::check($q, ['mapping' => ['c2' => 'y', 'c1' => 'x']]));
        $this->assertFalse(AnswerChecker::check($q, ['mapping' => ['c1' => 'x', 'c2' => 'x']]));
        $this->assertFalse(AnswerChecker::check($q, ['mapping' => ['c1' => 'x']]));
    }

    public function test_fix_needs_every_spot_right_untouched_counts_as_shown(): void
    {
        $q = ['mechanic' => 'fix', 'spots' => [
            ['id' => 'a', 'shown' => 'объекты', 'correct' => 'субъекты', 'options' => ['субъекты', 'объекты']],
            ['id' => 'b', 'shown' => 'рождения', 'correct' => 'рождения', 'options' => ['рождения', '14 лет']],
        ]];

        $this->assertTrue(AnswerChecker::check($q, ['spots' => ['a' => 'субъекты']]), 'верное место можно не трогать');
        $this->assertFalse(AnswerChecker::check($q, ['spots' => []]), 'ошибку не исправил');
        $this->assertFalse(AnswerChecker::check($q, ['spots' => ['a' => 'субъекты', 'b' => '14 лет']]), 'испортил верное');
        $this->assertSame(['spots' => ['a' => 'субъекты', 'b' => 'рождения']], AnswerChecker::solution($q));
    }

    public function test_public_view_of_fix_hides_correct_words(): void
    {
        $view = AnswerChecker::publicView(['id' => 'x', 'mechanic' => 'fix', 'prompt' => '?',
            'spots' => [['id' => 'a', 'shown' => 'объекты', 'correct' => 'субъекты', 'options' => ['субъекты', 'объекты']]]]);

        $this->assertArrayNotHasKey('correct', $view['spots'][0]);
        $this->assertSame('объекты', $view['spots'][0]['shown']);
    }

    public function test_public_view_hides_the_key(): void
    {
        $q = ['id' => 'x', 'mechanic' => 'multi', 'prompt' => '?', 'options' => [], 'correct' => ['a'],
            'explain_correct' => '+', 'explain_wrong' => '-', 'block' => 'law', 'level' => 3];

        $view = AnswerChecker::publicView($q);

        $this->assertArrayNotHasKey('correct', $view);
        $this->assertArrayNotHasKey('explain_wrong', $view);
        $this->assertArrayNotHasKey('level', $view);
        $this->assertSame('?', $view['prompt']);
    }
}
