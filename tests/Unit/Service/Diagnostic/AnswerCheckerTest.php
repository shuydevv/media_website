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

    public function test_chips_with_numeric_gap_keys(): void
    {
        // JSON {"1": "k1"} в PHP — целые ключи; со страницы приходят так же.
        $q = ['mechanic' => 'chips', 'answers' => json_decode('{"1":"k1","2":"k3"}', true)];

        $this->assertTrue(AnswerChecker::check($q, ['gaps' => json_decode('{"2":"k3","1":"k1"}', true)]));
        $this->assertFalse(AnswerChecker::check($q, ['gaps' => ['1' => 'k3', '2' => 'k1']]));
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
