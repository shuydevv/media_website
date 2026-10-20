<?php

namespace App\Service\Homework;

use Anthropic\Client;
use App\Models\HomeworkTask;
use App\Models\Submission;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * ИИ-черновик проверки письменного задания (17–25 и т.п.) через Claude API.
 * Результат — не оценка, а подсказка куратору: он видит балл, разбор по
 * элементам и флаг «проверь сам», и только после его подтверждения ученик
 * что-то получает (см. Mentor\ReviewAiController).
 *
 * Правила проверки живут в resources/ai/grading/*.md: base.md — общие для
 * любого предмета, плюс файл предмета (по названию категории). Весь этот
 * текст уходит в system и кешируется — он одинаков для всех запросов по
 * предмету, меняются только данные задания и ответ ученика.
 */
class ClaudeGrader
{
    /** Подстрока в Category::title → файл правил предмета. */
    private const SUBJECT_RULES = [
        'обществ' => 'obshchestvoznanie.md',
        'истор'   => 'istoriya.md',
    ];

    public function isEnabled(): bool
    {
        return (bool) config('anthropic.enabled') && (string) config('anthropic.key') !== '';
    }

    /**
     * Данные одного письменного задания из работы ученика — всё, что нужно
     * модели. Предмет — по категории задания в банке, а у «одноразового»
     * задания без банка — по категории курса домашки: в одной пачке бывают
     * работы по разным предметам.
     */
    public function payloadFor(Submission $submission, HomeworkTask $task): array
    {
        // Критерии и рекомендации — общие для номера (см. TaskCriteria);
        // у задания без банка их нет, тогда модель опирается на правила
        // предмета и образцовый ответ.
        $bank = $task->task;

        return [
            'subject'         => $this->subjectFor($submission, $task),
            'number'          => $task->number,
            'max_score'       => (int) ($task->max_score ?? 3),
            'question'        => $task->question_text,
            'passage'         => $task->passage_text,
            'image_path'      => $task->image_path,
            'criteria'        => $bank?->resolved_criteria,
            'recommendations' => $bank?->resolvedCriteriaRecord()?->comment,
            'exemplar'        => trim(implode("\n\n", array_filter([(string) $task->answer, (string) $task->explanation]))),
            'hint'            => $task->hint,
            'student_answer'  => (string) (($submission->answers ?? [])[(string) $task->id] ?? ''),
        ];
    }

    public function subjectFor(Submission $submission, HomeworkTask $task): ?string
    {
        return $task->task?->category?->title ?? $submission->homework?->course?->category?->title;
    }

    /**
     * Поштучная проверка (кнопка «AI-проверка» у задания).
     *
     * @param array{
     *   subject?: ?string, number?: ?string, max_score: int,
     *   question?: ?string, passage?: ?string, image_path?: ?string,
     *   criteria?: ?string, recommendations?: ?string, exemplar?: ?string,
     *   student_answer: string,
     * } $task
     * @return array{
     *   score:int, rationale:string, comment:string,
     *   needs_review:bool, review_reason:string,
     *   elements:array, criteria_scores:array, extra_errors:array, model:string,
     * }
     */
    public function grade(array $task): array
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException('ИИ-проверка выключена: не задан ANTHROPIC_API_KEY.');
        }

        // Пустой ответ проверять моделью незачем.
        if (trim($task['student_answer']) === '') {
            return $this->result(0, 'Ответ отсутствует.', 'Ответа на это задание нет.', false, '', [], [], [], 'none');
        }

        $message = $this->client()->beta->messages->create(
            ...$this->requestParams($task),
            // Если основная модель откажется отвечать (ложное срабатывание
            // фильтров безопасности), запрос сам повторится на подходящей
            // модели внутри того же вызова. В Batch API этого параметра нет.
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
        );

        return $this->parse($message, (int) $task['max_score']);
    }

    /**
     * Параметры запроса — общие для поштучной проверки и пачки
     * (ClaudeBatchGrader кладёт их в params элемента пачки).
     */
    public function requestParams(array $task): array
    {
        return [
            'model'        => (string) config('anthropic.model'),
            'maxTokens'    => 16000,
            'system'       => [[
                'type'         => 'text',
                'text'         => $this->systemPrompt($task['subject'] ?? null),
                'cacheControl' => ['type' => 'ephemeral'],
            ]],
            'messages'     => [['role' => 'user', 'content' => $this->userContent($task, max(0, (int) $task['max_score']))]],
            'thinking'     => ['type' => 'adaptive'],
            'outputConfig' => [
                'effort' => (string) config('anthropic.effort'),
                'format' => ['type' => 'json_schema', 'schema' => self::schema()],
            ],
        ];
    }

    /**
     * Ответ модели → черновик. $message — Message из пачки или BetaMessage
     * из поштучного вызова: нужные поля у них одинаковые.
     */
    public function parse(object $message, int $maxScore): array
    {
        $maxScore = max(0, $maxScore);

        Log::info('ClaudeGrader usage', [
            'model'       => $message->model,
            'stop'        => $message->stopReason,
            'input'       => $message->usage->inputTokens,
            'cache_read'  => $message->usage->cacheReadInputTokens,
            'cache_write' => $message->usage->cacheCreationInputTokens,
            'output'      => $message->usage->outputTokens,
        ]);

        if ($message->stopReason === 'refusal') {
            throw new RuntimeException('Модель отказалась проверять этот ответ — проверьте вручную.');
        }
        if ($message->stopReason === 'max_tokens') {
            throw new RuntimeException('Ответ модели обрезан по лимиту токенов — попробуйте ещё раз.');
        }

        $data = null;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);
                break;
            }
        }
        if (!is_array($data)) {
            throw new RuntimeException('Модель вернула ответ не в ожидаемом формате.');
        }

        return $this->draftFromData($data, $maxScore, $message->model);
    }

    /**
     * Результат проверки в формате схемы (см. schema()) → черновик для
     * ai_drafts. Общий для API и для результата из Claude Desktop
     * (DesktopGradingExchange) — там те же поля.
     */
    public function draftFromData(array $data, int $maxScore, string $model): array
    {
        $maxScore = max(0, $maxScore);
        $score = (int) ($data['score'] ?? 0);
        $clamped = max(0, min($maxScore, $score));
        $needsReview = (bool) ($data['needs_human_review'] ?? false);
        $reviewReason = trim((string) ($data['review_reason'] ?? ''));

        // Балл вне шкалы — признак того, что модель неверно поняла максимум
        // (например, у школы шкала отличается от ФИПИ). Не молча режем, а
        // показываем куратору.
        if ($clamped !== $score) {
            $needsReview = true;
            $reviewReason = trim("Модель поставила {$score} при максимуме {$maxScore}. " . $reviewReason);
        }

        return $this->result(
            $clamped,
            trim((string) ($data['rationale'] ?? '')),
            trim((string) ($data['comment'] ?? '')),
            $needsReview,
            $reviewReason,
            (array) ($data['elements'] ?? []),
            (array) ($data['criteria_scores'] ?? []),
            (array) ($data['extra_elements_with_errors'] ?? []),
            $model,
        );
    }

    private function result(
        int $score, string $rationale, string $comment, bool $needsReview, string $reviewReason,
        array $elements, array $criteriaScores, array $extraErrors, string $model,
    ): array {
        return [
            'score'           => $score,
            'rationale'       => $rationale,
            'comment'         => $comment,
            'needs_review'    => $needsReview,
            'review_reason'   => $reviewReason,
            'elements'        => $elements,
            'criteria_scores' => $criteriaScores,
            'extra_errors'    => $extraErrors,
            'model'           => $model,
        ];
    }

    public function client(): Client
    {
        $guzzle = [
            'timeout'         => (int) config('anthropic.timeout'),
            'connect_timeout' => 15,
            'verify'          => config('anthropic.verify'),
        ];
        $proxy = (string) config('anthropic.proxy');
        if ($proxy !== '') {
            $guzzle['proxy'] = $proxy;
        }

        $baseUrl = (string) config('anthropic.base_url');

        return new Client(
            apiKey: (string) config('anthropic.key'),
            baseUrl: $baseUrl !== '' ? $baseUrl : null,
            requestOptions: ['transporter' => new GuzzleClient($guzzle), 'maxRetries' => 2],
        );
    }

    /**
     * Детерминированный текст: любой байт, меняющийся между запросами
     * (дата, id), сломал бы кеш промпта.
     */
    private function systemPrompt(?string $subject): string
    {
        $dir = resource_path('ai/grading');
        $text = file_get_contents($dir . '/base.md');

        $file = $this->subjectRulesFile($subject);
        if ($file !== null) {
            return $text . "\n\n" . file_get_contents($dir . '/' . $file);
        }

        // Текст одинаковый для всех предметов без своих правил — кеш общий.
        return $text . "\n\n# Правила по предмету\n\n"
            . 'Отдельных правил по этому предмету в инструкции нет. Опирайся на критерии и рекомендации школы '
            . 'из запроса и на общие подходы ФИПИ к оцениванию заданий этого предмета, которые ты знаешь. '
            . 'Если критериев школы нет и шкала для номера тебе неизвестна точно, включай needs_human_review.';
    }

    /** null — для предмета нет отдельного файла правил, проверка по base.md и критериям школы. */
    public function subjectRulesFile(?string $subject): ?string
    {
        $subjectLower = Str::lower((string) $subject);
        if ($subjectLower === '') {
            return null;
        }
        foreach (self::SUBJECT_RULES as $needle => $file) {
            if (Str::contains($subjectLower, $needle)) {
                return $file;
            }
        }
        return null;
    }

    private function userContent(array $task, int $maxScore): array
    {
        $section = function (string $tag, ?string $value): string {
            $value = trim((string) $value);
            return "<{$tag}>\n" . ($value !== '' ? $value : '(нет)') . "\n</{$tag}>";
        };

        $text = implode("\n\n", [
            'Предмет: ' . (trim((string) ($task['subject'] ?? '')) ?: 'не указан'),
            'Номер задания в ЕГЭ: ' . (trim((string) ($task['number'] ?? '')) ?: 'не указан'),
            "Максимальный балл: {$maxScore}",
            $section('task_text', $task['question'] ?? null),
            $section('source_text', $task['passage'] ?? null),
            $section('criteria', $task['criteria'] ?? null),
            $section('school_recommendations', $task['recommendations'] ?? null),
            $section('exemplar_answer', $task['exemplar'] ?? null),
            $section('hint', $task['hint'] ?? null),
            $section('student_answer', $task['student_answer']),
            'Проверь ответ ученика по правилам и верни результат.',
        ]);

        $content = [];
        if ($image = $this->imageBlock($task['image_path'] ?? null)) {
            $content[] = $image;
        }
        $content[] = ['type' => 'text', 'text' => $text];

        return $content;
    }

    /** Картинка к заданию (график в 21 и т.п.) — с публичного диска, как в task-prompt.blade.php. */
    private function imageBlock(?string $path): ?array
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return ['type' => 'image', 'source' => ['type' => 'url', 'url' => $path]];
        }

        $path = Str::after($path, '/storage/');
        $mime = match (Str::lower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            default       => null,
        };
        $disk = Storage::disk('public');
        if ($mime === null || !$disk->exists($path)) {
            Log::warning('ClaudeGrader: картинка задания не найдена', ['path' => $path]);
            return null;
        }

        return ['type' => 'image', 'source' => [
            'type'      => 'base64',
            'mediaType' => $mime,
            'data'      => base64_encode($disk->get($path)),
        ]];
    }

    /** Порядок полей = порядок, в котором модель их пишет: сначала разбор, потом балл. */
    private static function schema(): array
    {
        $str = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => [
                'elements' => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'element'         => $str,
                            'student_wrote'   => $str,
                            'counted'         => ['type' => 'boolean'],
                            'reason'          => $str,
                        ],
                        'required' => ['element', 'student_wrote', 'counted', 'reason'],
                        'additionalProperties' => false,
                    ],
                ],
                'extra_elements_with_errors' => ['type' => 'array', 'items' => $str],
                'criteria_scores' => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'criterion' => $str,
                            'score'     => ['type' => 'integer'],
                            'max_score' => ['type' => 'integer'],
                        ],
                        'required' => ['criterion', 'score', 'max_score'],
                        'additionalProperties' => false,
                    ],
                ],
                'score'              => ['type' => 'integer'],
                'rationale'          => $str,
                'comment'            => $str,
                'needs_human_review' => ['type' => 'boolean'],
                'review_reason'      => $str,
            ],
            'required' => [
                'elements', 'extra_elements_with_errors', 'criteria_scores', 'score',
                'rationale', 'comment', 'needs_human_review', 'review_reason',
            ],
            'additionalProperties' => false,
        ];
    }
}
