<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Service\OpenAIService;

class OpenAIServiceTest extends TestCase
{
    /** @test */
    public function it_pings_openai_successfully()
    {
        config()->set('openai.key', 'test-key');
        config()->set('openai.model', 'gpt-5');
        config()->set('openai.base', 'https://api.openai.com');

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_123',
                'output_text' => 'pong',
            ], 200),
        ]);

        $svc = new OpenAIService();
        $resp = $svc->ping();

        $this->assertIsArray($resp);
        $this->assertEquals('resp_123', $resp['id'] ?? null);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['model'] === 'gpt-5'
                && $request['input'] === 'ping';
        });
    }

    /** @test */
    public function it_builds_score_draft_from_ai_json()
    {
        config()->set('openai.key', 'test-key');
        config()->set('openai.model', 'gpt-5');
        config()->set('openai.base', 'https://api.openai.com');

        $fakeJson = json_encode([
            'score' => 2,
            'explanation' => 'Краткое объяснение',
            'recommendation' => 'Добавь примеры',
        ], JSON_UNESCAPED_UNICODE);

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_456',
                'output_text' => $fakeJson,
            ], 200),
        ]);

        $svc = new OpenAIService();

        $result = $svc->draftScore(
            studentAnswer: "Ответ ученика",
            criteria: "Критерии проверки",
            maxScore: 3,
            exemplar: "Эталонный ответ"
        );

        $this->assertSame(2, $result['score']);
        $this->assertSame('Краткое объяснение', $result['explanation']);
        $this->assertSame('Добавь примеры', $result['recommendation']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['model'] === 'gpt-5'
                && is_array($request['input'])
                && $request['response_format']['type'] === 'json_object';
        });
    }
}
