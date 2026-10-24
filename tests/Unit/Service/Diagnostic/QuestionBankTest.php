<?php

namespace Tests\Unit\Service\Diagnostic;

use App\Service\Diagnostic\QuestionBank;
use PHPUnit\Framework\TestCase;

class QuestionBankTest extends TestCase
{
    public function test_shipped_bank_is_valid(): void
    {
        $bank = QuestionBank::fromFile(dirname(__DIR__, 4).'/docs/diagnostic/questions.json');

        $this->assertSame([], $bank->validate(['society', 'economy', 'social', 'politics', 'law']));
    }

    public function test_rules(): void
    {
        $q = fn (string $id, int $level, string $mechanic, array $extra) => $extra + [
            'id' => $id, 'block' => 'law', 'level' => $level, 'mechanic' => $mechanic,
            'prompt' => '?', 'explain_correct' => '+', 'explain_wrong' => '-',
        ];
        $bank = new QuestionBank([
            's2' => $q('s2', 2, 'swipe', ['answer' => true]),
            'h' => $q('h', 3, 'single', ['options' => [['id' => 'a'], ['id' => 'b']], 'correct' => 'a']),
            'm' => $q('m', 3, 'multi', ['options' => [['id' => 'a']], 'correct' => ['z']]),
            'b' => $q('b', 2, 'buckets', ['buckets' => [['id' => 'x']], 'cards' => [['id' => 'c1'], ['id' => 'c2']], 'mapping' => ['c1' => 'x']]),
            'c' => $q('c', 2, 'chips', ['text' => '{{1}} и {{2}}', 'chips' => [['id' => 'k']], 'answers' => ['1' => 'k']]),
        ]);

        $errors = implode("\n", $bank->validate(['law']));

        $this->assertStringContainsString('«s2»: свайп — только на лёгком', $errors);
        $this->assertStringContainsString('«h»: на сложном уровне — только multi', $errors);
        $this->assertStringContainsString('«m»: correct', $errors);
        $this->assertStringContainsString('«b»: mapping', $errors);
        $this->assertStringContainsString('«c»: в text пропуски', $errors);
        $this->assertStringContainsString('Блок law, уровень 1: вопросов 0', $errors);
    }

    public function test_pick_prefers_unseen_and_skips_used(): void
    {
        $q = fn ($id) => ['id' => $id, 'block' => 'law', 'level' => 1];
        $bank = new QuestionBank(['a' => $q('a'), 'b' => $q('b'), 'c' => $q('c')]);

        for ($i = 0; $i < 20; $i++) {
            $this->assertSame('c', $bank->pick('law', 1, ['a'], ['b'])['id']);
        }
        $this->assertSame('b', $bank->pick('law', 1, ['a', 'c'], ['b'])['id'], 'все видел — повтор лучше, чем ничего');
        $this->assertNull($bank->pick('law', 1, ['a', 'b', 'c']));
    }
}
