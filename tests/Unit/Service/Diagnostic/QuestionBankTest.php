<?php

namespace Tests\Unit\Service\Diagnostic;

use App\Service\Diagnostic\QuestionBank;
use PHPUnit\Framework\TestCase;

class QuestionBankTest extends TestCase
{
    public function test_shipped_bank_is_valid(): void
    {
        $bank = QuestionBank::fromFile(dirname(__DIR__, 4).'/docs/diagnostic/questions.json');

        $this->assertSame([], $bank->validate(['society', 'economy', 'social', 'politics', 'law'], ['squid', 'turtle', 'puffer']));
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
            'c' => $q('c', 2, 'chips', ['text' => '{{1}}', 'chips' => [['id' => 'k']], 'answers' => ['1' => 'k']]),
            'f' => $q('f', 3, 'fix', ['text' => '{{a}} и {{b}}', 'spots' => [
                ['id' => 'a', 'shown' => 'x', 'correct' => 'y', 'options' => ['x', 'y']],
                ['id' => 'b', 'shown' => 'x', 'correct' => 'y', 'options' => ['x', 'y']]]]),
            'w' => $q('w', 1, 'single', ['skin' => 'who', 'from' => 'nobody',
                'speakers' => [['id' => 'p', 'who' => 'petya', 'text' => '…'], ['id' => 'm', 'who' => 'turtle', 'text' => '…']], 'correct' => 'p']),
            'd' => $q('d', 3, 'multi', ['skin' => 'debate', 'options' => [['id' => 'a']], 'correct' => ['a']]),
            'k' => $q('k', 2, 'buckets', ['skin' => 'odd', 'buckets' => [['id' => 'x']], 'cards' => [['id' => 'c1']], 'mapping' => ['c1' => 'x']]),
        ]);

        $errors = implode("\n", $bank->validate(['law'], ['turtle']));

        $this->assertStringContainsString('«s2»: свайп — только на лёгком', $errors);
        $this->assertStringContainsString('«h»: на сложном уровне — только multi', $errors);
        $this->assertStringContainsString('«m»: correct', $errors);
        $this->assertStringContainsString('«b»: mapping', $errors);
        $this->assertStringContainsString('«c»: пропуски (chips) больше не используем', $errors);
        $this->assertStringContainsString('«f»: нужно хотя бы одно верное место', $errors);
        $this->assertStringContainsString('«w»: нет персонажа petya', $errors);
        $this->assertStringContainsString('«w»: нет персонажа nobody', $errors);
        $this->assertStringContainsString('«d»: у оформления debate нужен from', $errors);
        $this->assertStringContainsString('«k»: у buckets нет оформления odd', $errors);
        $this->assertStringNotContainsString('«f»: на сложном уровне', $errors, 'fix — можно на сложном');
        $this->assertStringContainsString('Блок law, уровень 1: вопросов 1', $errors);
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

    public function test_pick_prefers_mechanic_not_used_yet_in_attempt(): void
    {
        $q = fn ($id, $block, $mechanic, $skin = null) => ['id' => $id, 'block' => $block, 'level' => 1, 'mechanic' => $mechanic, 'skin' => $skin];
        $bank = new QuestionBank([
            'p1' => $q('p1', 'politics', 'swipe', 'chat'),
            'p2' => $q('p2', 'politics', 'single', 'who'),
            'e1' => $q('e1', 'economy', 'swipe', 'chat'),
            'e2' => $q('e2', 'economy', 'single', 'who'),
            'e3' => $q('e3', 'economy', 'single', 'odd'),
        ]);

        for ($i = 0; $i < 20; $i++) {
            // В политике уже были чат и «Кто прав?» — в экономике «Найди лишнее»,
            // даже если его человек уже видел.
            $this->assertSame('e3', $bank->pick('economy', 1, ['p1', 'p2'], ['e3'])['id']);
            // Был только чат — любой, кроме чата.
            $this->assertContains($bank->pick('economy', 1, ['p1'])['id'], ['e2', 'e3']);
        }
    }
}
