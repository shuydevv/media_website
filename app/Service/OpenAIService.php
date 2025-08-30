<?php

namespace App\Service;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    protected string $key;
    protected string $model;
    protected string $base;
    protected $verify;
    protected bool $enabled;
    protected bool $useMock;
    protected int $timeout;

    public function __construct()
    {
        $this->key     = (string) config('openai.key');
        $this->model   = (string) config('openai.model', 'gpt-5');
        $this->base    = rtrim((string) config('openai.base', 'https://api.openai.com'), '/');
        $this->verify  = config('openai.verify', true);
        $this->enabled = (bool) config('openai.enabled', true);
        $this->useMock = (bool) config('openai.use_mock', false);
        $this->timeout = (int)  env('OPENAI_TIMEOUT', 90);
    }

    public function ping(): array
    {
        $payload = [
            'model' => $this->model,
            'input' => 'ping',
        ];

        Log::debug('AI payload (ping)', ['payload' => $payload]);

        $resp = $this->httpClient()->post($this->base . '/v1/responses', $payload);

        if ($resp->failed()) {
            throw new RequestException($resp);
        }

        return $resp->json();
    }

    /**
     * Возвращает черновик проверки:
     * [
     *   'score' => int (0..$maxScore),
     *   'rationale' => string,
     *   'comment' => string,
     *   // алиасы:
     *   'explanation' => string,
     *   'recommendation' => string,
     * ]
     */
    public function draftScore(
        string  $studentAnswer,
        string  $criteria = '',
        int     $maxScore = 3,
        ?string $exemplar = null,
        string  $comment = ''
    ): array {
        // Мок для локалки/дев-режима
        if (!$this->enabled || $this->useMock) {
            $mock = [
                'score'          => min($maxScore, 2),
                'rationale'      => 'Мок: логичная структура ответа, раскрыты ключевые пункты.',
                'comment'        => 'Мок: уточни терминологию и добавь пример.',
                'explanation'    => 'Мок: логичная структура ответа, раскрыты ключевые пункты.',
                'recommendation' => 'Мок: уточни терминологию и добавь пример.',
            ];
            Log::debug('AI draftScore (mock used)', $mock);
            return $mock;
        }

        $exemplar = (string) ($exemplar ?? '');

        // Строгий system-промпт — просим только JSON
        $system = 'Ты опытный экзаменатор ЕГЭ. Проверь работу по указанным критериям проверки. Проверяй строго, но справедливо. Не завышай и не занижай баллы
        Ориентируйся на образец ответа, не придумывай свои критерии. Следи, чтобы ответы ученика отвечали на поставленный вопрос, были логичны и не содержали логических и фактических ошибок.
        Если ответ состоит только из "воды" — не засчитывай его. Обращайся к ученику "на ты" и формулируй мысли простыми словами. Когда ты принимаешь решение, правильный ли ответ дал ученик, ОБЯЗАТЕЛЬНО смотри, соответствует ли его ответ вопросу задания. Ответ может быть в целом верным и логичным, но по другой теме — такие ответы засчитывать нельзя! Обязательно сообщи ученику, если его ответ не правильный и не по теме. ВАЖНО! Если ответы ученика не по теме — не пытайся их исправить. Просто сообщи, что формулировка не отвечает на вопрос задания. Учитывай поле «Рекомендации по проверке», если оно не пустое, однако не используй прямых заимствований и формулировок оттуда в своих ответах. Если в рекомендациях указаны примеры хороших и плохих ответов, они обычно по другой теме, а не по теме задания. Поэтому если будешь их использовать, проводи параллель с вопросом актуального задания, чтобы дать правильный ответ. Не используй эти примеры для обоснования своего ответа ученику. Если ответ ученика по теме, но неверный, можешь исправить его в поле "Комментарий ученику", но если ответ не по теме — лучше дай рекоммендации на основе образца ответа на полный балл Отвечай ТОЛЬКО корректным JSON-объектом ровно вида {"score": int, "rationale": string, "comment": string}. Без каких-либо пояснений, преамбул, ``` и дополнительного текста.';

        $userPrompt = <<<TXT
Ты проверяешь задание ЕГЭ.
Максимальный балл: {$maxScore}.
Образец ответа (на полный балл): {$exemplar}
Критерии проверки: {$criteria}
Рекомендации по проверке: {$comment}

Ответ ученика:
{$studentAnswer}
TXT;

        $payload = [
            'model' => $this->model,
            'input' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $userPrompt],
            ],
            // НЕ используем устаревший response_format; на новых версиях можно добавить text.format, но мы жёстко требуем JSON промптом.
        ];

        Log::debug('AI payload (draftScore)', [
            'has_studentAnswer' => mb_strlen($studentAnswer) > 0,
            'has_criteria'      => mb_strlen($criteria) > 0,
            'has_comment'       => mb_strlen($comment) > 0,
            'maxScore'          => $maxScore,
        ]);

        try {
            $resp = $this->httpClient()->post($this->base . '/v1/responses', $payload);

            // Региональная блокировка — вернём безопасный локальный фоллбек
            if ($resp->status() === 403) {
                $code = data_get($resp->json(), 'error.code');
                Log::warning('AI 403', ['code' => $code]);
                if ($code === 'unsupported_country_region_territory') {
                    $fallback = $this->localFallbackDraft($studentAnswer, $criteria, $exemplar, $maxScore);
                    Log::debug('AI fallback (region)', $fallback);
                    return $fallback;
                }
            }

            if ($resp->failed()) {
                $body = $resp->json();
                Log::error('AI HTTP failed', ['status' => $resp->status(), 'body' => $body]);

                // Для локалки возвращаем осмысленный фоллбек, чтобы UI не пустел
                if (app()->environment('local')) {
                    $fallback = $this->localFallbackDraft($studentAnswer, $criteria, $exemplar, $maxScore);
                    Log::debug('AI fallback (failed http)', $fallback);
                    return $fallback;
                }

                throw new RequestException($resp);
            }

            $j = $resp->json();
            Log::debug('AI responses raw', [
                'has_output_text' => isset($j['output_text']),
                'has_output'      => isset($j['output']),
            ]);

            $data = $this->extractAssistantJson($j);

            // Нормализация
            $score       = (int)   ($data['score'] ?? 0);
            $rationale   = trim((string)($data['rationale'] ?? $data['explanation'] ?? $data['reason'] ?? ''));
            $commentText = trim((string)($data['comment']   ?? $data['recommendation'] ?? $data['advice'] ?? ''));

            Log::debug('AI draftScore parsed', [
                'score'     => $score,
                'rationale' => mb_substr($rationale, 0, 200),
                'comment'   => mb_substr($commentText, 0, 200),
                'data_raw'  => $data,
            ]);

            // Если модель вернула пустоту — локальный фоллбек, чтобы поля не были пустыми
            if ($rationale === '' && $commentText === '') {
                $fallback = $this->localFallbackDraft($studentAnswer, $criteria, $exemplar, $maxScore);
                $fallback['score'] = max(0, min($maxScore, $score ?: $fallback['score']));
                Log::debug('AI fallback (empty fields)', $fallback);
                return $fallback;
            }

            return [
                'score'          => max(0, min($maxScore, $score)),
                'rationale'      => $rationale,
                'comment'        => $commentText,
                // Алиасы для совместимости
                'explanation'    => $rationale,
                'recommendation' => $commentText,
            ];
        } catch (\Throwable $e) {
            Log::error('AI exception', ['message' => $e->getMessage()]);

            // В dev возвращаем фоллбек, чтобы интерфейс был заполнен
            if (app()->environment('local')) {
                $fallback = $this->localFallbackDraft($studentAnswer, $criteria, $exemplar, $maxScore);
                Log::debug('AI fallback (exception)', $fallback);
                return $fallback;
            }

            throw $e;
        }
    }

    /**
     * Совместимый алиас для старого кода.
     */
    public function generateReviewDraft(
        string  $studentAnswer,
        ?string $exemplar = null,
        string  $criteria = '',
        string  $comment = '',
        int     $maxScore = 3
    ): array {
        return $this->draftScore(
            studentAnswer: $studentAnswer,
            criteria:      $criteria,
            maxScore:      $maxScore,
            exemplar:      $exemplar,
            comment:       $comment,
        );
    }

    /**
     * Единая точка настройки HTTP-клиента с устойчивыми опциями.
     */
    protected function httpClient()
    {
        $options = [
            'verify' => $this->verify,
            'curl'   => [
                // На Windows/локалке часто спасает от таймаутов
                CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
                // Убираем "подвисшие" соединения
                CURLOPT_TCP_KEEPALIVE  => 1,
                CURLOPT_TCP_KEEPIDLE   => 15,
                CURLOPT_TCP_KEEPINTVL  => 15,
            ],
        ];

        // Если в конфиге есть прокси — используем. Если нет — идём напрямую.
        $proxy = (string) (config('openai.proxy') ?? '');
        if ($proxy !== '') {
            $options['proxy'] = [
                'http'  => $proxy,
                'https' => $proxy,
                'no'    => 'localhost,127.0.0.1,::1',
            ];
            // HTTPS через HTTP-прокси
            $options['curl'][CURLOPT_HTTPPROXYTUNNEL] = 1;
        }

        // Лог один раз на запрос (без ключей/секретов)
        Log::debug('OpenAI HTTP options', [
            'has_proxy' => $proxy !== '',
            'verify'    => is_bool($this->verify) ? $this->verify : (string)$this->verify,
        ]);

        return Http::withToken($this->key)
            ->acceptJson()->asJson()
            ->timeout($this->timeout)
            ->connectTimeout(10)
            ->retry(3, 2000, throw: false)
            // Убираем задержки на 100-continue
            ->withHeaders(['Expect' => ''])
            ->withOptions($options);
    }

    /**
     * Парсинг ответа Responses API:
     * - сначала ищем content[*].json
     * - затем пытаемся вытащить JSON из текстового ответа (включая ```json ... ``` и первый {...})
     */
    protected function extractAssistantJson(array $j): array
    {
        // 1) content[*].json / content[*].text
        $output = $j['output'] ?? null;
        if (is_array($output)) {
            foreach ($output as $chunk) {
                $content = $chunk['content'] ?? null;
                if (!is_array($content)) continue;
                foreach ($content as $c) {
                    if (isset($c['json']) && is_array($c['json'])) {
                        return $c['json'];
                    }
                    if (isset($c['text']) && is_string($c['text'])) {
                        $maybe = $this->decodeJsonLoose($c['text']);
                        if (is_array($maybe)) return $maybe;
                    }
                }
            }
        }

        // 2) output_text / output.text
        $text = $j['output_text']
            ?? ($j['output']['text'] ?? null)
            ?? (is_array($output) ? data_get($j, 'output.0.content.0.text') : null);

        if (is_string($text)) {
            $maybe = $this->decodeJsonLoose($text);
            if (is_array($maybe)) return $maybe;
        }

        return [];
    }

    /**
     * Локальный фоллбек — осмысленные тексты без API.
     */
    protected function localFallbackDraft(string $answer, string $criteria, string $exemplar, int $maxScore): array
    {
        $answer = trim((string)$answer);
        $crit   = trim((string)$criteria);
        $ex     = trim((string)$exemplar);

        // Грубая эвристика для балла
        $len   = str_word_count(strip_tags($answer), 0, 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнопрстуфхцчшщъыьэюя');
        $score = $answer === '' ? 0 : ($len < 20 ? max(0, (int) floor($maxScore * 0.3)) : max(1, (int) round($maxScore * 0.6)));

        $keywords = $this->pickKeywords($crit . ' ' . $ex, 6);
        $missed   = [];
        foreach ($keywords as $kw) {
            if ($kw !== '' && mb_stripos($answer, $kw) === false) {
                $missed[] = $kw;
            }
        }

        $rationale = $answer === ''
            ? 'Ответ отсутствует'
            : 'Ошибка';

        if (!empty($missed)) {
            $rationale .= ' Не отражены: ' . implode(', ', array_slice($missed, 0, 3)) . '.';
        }

        $comment = $answer === ''
            ? 'Ответ отстутствует'
            : 'Ошибка.';

        return [
            'score'          => max(0, min($maxScore, $score)),
            'rationale'      => $rationale,
            'comment'        => $comment,
            'explanation'    => $rationale,
            'recommendation' => $comment,
        ];
    }

    /**
     * Извлекаем JSON из строки (снимаем ```json и берём первый {...})
     */
    protected function decodeJsonLoose(string $text): array|null
    {
        $clean = trim($text);
        $clean = preg_replace('/^\s*```json\s*/i', '', $clean);
        $clean = preg_replace('/^\s*```\s*/i', '', $clean);
        $clean = preg_replace('/\s*```$/', '', $clean);

        $data = json_decode($clean, true);
        if (is_array($data)) return $data;

        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $clean, $m)) {
            $data = json_decode($m[0], true);
            if (is_array($data)) return $data;
        }

        return null;
    }

    /**
     * Простейший выбор ключевых слов.
     */
    protected function pickKeywords(string $text, int $limit = 6): array
    {
        $text  = mb_strtolower(strip_tags($text));
        $text  = preg_replace('/[^a-zа-яё0-9\s-]/iu', ' ', $text);
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        $stop = [
            'и','в','во','не','что','он','она','они','мы','вы','к','ко','на','по','из','за','для',
            'как','так','же','а','но','или','ли','о','об','от','до','при','над','под','это','той',
            'того','т.д','т','д','с','со','у','же','бы','чтоб','чтобы','либо','ни','да','нет',
            'the','and','or','but','to','of','in','on','for','with','as','by','an','a','is','are',
        ];

        $freq = [];
        foreach ($words as $w) {
            if (mb_strlen($w) < 4) continue;
            if (in_array($w, $stop, true)) continue;
            $freq[$w] = ($freq[$w] ?? 0) + 1;
        }

        arsort($freq);
        return array_slice(array_keys($freq), 0, $limit);
    }
}
