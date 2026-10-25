<?php

namespace App\Service\Diagnostic;

use App\Models\DiagnosticAnswer;
use App\Models\DiagnosticAttempt;
use App\Models\TelegramContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Прохождение диагностики: экран, на котором человек сейчас, приём ответа,
 * итог. Сервер — источник правды (ТЗ, раздел 4.3): страница получает вопрос
 * без ключа ответа, присылает ответ, сервер проверяет, пишет в лог и решает,
 * что дальше. Закрыл Mini App и открыл снова — state() вернёт тот же вопрос.
 *
 * Экраны (state()['screen']):
 *   profile  — «Ты в каком классе?» (класса ещё нет);
 *   question — текущий вопрос текущего блока;
 *   target   — блоки пройдены, «На сколько хочешь сдать?»;
 *   result   — итог (после цели).
 */
class DiagnosticRunner
{
    public function __construct(
        private readonly QuestionBank $bank,
        private readonly ScoreModel $model,
    ) {
    }

    public static function make(): self
    {
        return new self(QuestionBank::fromConfig(), ScoreModel::fromConfig());
    }

    public function start(?TelegramContact $contact, string $entry, ?string $platform = null): DiagnosticAttempt
    {
        // Порядок блоков — один для всех (config diagnostic.block_order, ТЗ 5.1);
        // блоки, которых там нет, — в конце.
        $all = array_keys(config('diagnostic.blocks'));
        $order = array_values(array_unique(array_merge(array_intersect(config('diagnostic.block_order', []), $all), $all)));

        return DiagnosticAttempt::create([
            'token' => Str::random(32),
            'telegram_contact_id' => $contact?->id,
            'entry' => $entry,
            'platform' => $platform,
            // Класс не переносим: каждое прохождение, в том числе после
            // «Начать заново», — с самого начала, со знакомства (решение автора).
            'block_order' => $order,
            // Явно, а не дефолтами БД: create() их в модель не подтягивает,
            // а state() читает модель сразу после создания.
            'status' => DiagnosticAttempt::STATUS_ACTIVE,
            'block_index' => 0,
            'streak' => 0,
            'started_at' => now(),
        ]);
    }

    /** Последнее прохождение контакта (незаконченное или законченное). */
    public function latestFor(TelegramContact $contact): ?DiagnosticAttempt
    {
        return DiagnosticAttempt::where('telegram_contact_id', $contact->id)->latest('id')->first();
    }

    public function setGrade(DiagnosticAttempt $attempt, string $grade): void
    {
        $attempt->forceFill(['grade' => $grade])->save();
    }

    public function state(DiagnosticAttempt $attempt): array
    {
        if ($attempt->isFinished()) {
            $screen = ['screen' => 'result', 'result' => $this->resultView($attempt), 'improve' => $attempt->improve];
        } elseif ($attempt->grade === null) {
            $screen = ['screen' => 'profile', 'grades' => config('diagnostic.grades')];
        } elseif ($question = $this->currentQuestion($attempt)) {
            $screen = ['screen' => 'question', 'question' => AnswerChecker::publicView($question)];
        } else {
            $screen = ['screen' => 'target', 'default_target' => config('diagnostic.model.default_target')];
        }

        // progress — после currentQuestion(): выдача вопроса может перейти к следующему блоку.
        return ['attempt' => $attempt->token, 'progress' => $this->progress($attempt)] + $screen;
    }

    /**
     * @param  array  $payload  ответ по механике (AnswerChecker)
     * @param  int|null  $explainMs  сколько человек читал пояснение к прошлому ответу
     *
     * @throws StaleAnswerException вопрос уже не текущий (повторная отправка, вторая вкладка)
     */
    public function answer(DiagnosticAttempt $attempt, string $questionId, array $payload, bool $guess, ?int $answerMs, ?int $explainMs): array
    {
        return DB::transaction(function () use ($attempt, $questionId, $payload, $guess, $answerMs, $explainMs) {
            $attempt = DiagnosticAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $question = $attempt->current_question_id ? $this->bank->get($attempt->current_question_id) : null;
            if ($attempt->isFinished() || $attempt->current_question_id !== $questionId || $question === null) {
                throw new StaleAnswerException($attempt);
            }

            $block = $question['block'];
            // «Не знаю» ($guess) — пропуск: ноль баллов, путь блока — как после ошибки.
            $correct = ! $guess && AnswerChecker::check($question, $payload);

            if ($explainMs !== null) {
                DiagnosticAnswer::where('diagnostic_attempt_id', $attempt->id)->latest('id')->limit(1)
                    ->update(['explain_ms' => min($explainMs, 3_600_000)]);
            }

            $before = $this->blockAnswers($attempt, $block);
            DiagnosticAnswer::create([
                'diagnostic_attempt_id' => $attempt->id,
                'block' => $block,
                'level' => (int) $question['level'],
                'position' => count($before) + 1,
                'question_id' => $question['id'],
                'question_hash' => QuestionBank::hash($question),
                'mechanic' => $question['mechanic'],
                'answer' => $payload,
                'correct' => $correct,
                'guess' => $guess,
                'answer_ms' => $answerMs !== null ? min(max($answerMs, 0), 3_600_000) : null,
                'created_at' => now(),
            ]);

            $after = $this->blockAnswers($attempt, $block);
            $blockDone = BlockRun::nextLevel($after) === null;
            if ($blockDone) {
                $attempt->block_index++;
            }
            $attempt->forceFill([
                'current_question_id' => null,
                'streak' => $correct ? $attempt->streak + 1 : 0,
                'last_answer_at' => now(),
            ])->save();

            return [
                'correct' => $correct,
                'skipped' => $guess,
                'explain' => $correct ? $question['explain_correct'] : $question['explain_wrong'],
                // Кто говорит пояснение: в «Кто прав?» — тот, кто прав; иначе —
                // explain_by из банка; пусто — рыба.
                'explain_by' => $this->explainBy($question),
                'meme' => $question['meme'] ?? null,
                'solution' => AnswerChecker::solution($question),
                'streak' => $attempt->streak,
                'block' => [
                    'key' => $block,
                    'title' => config("diagnostic.blocks.{$block}.title"),
                    'finished' => $blockDone,
                    'closed_by_errors' => $blockDone && BlockRun::closedByErrors($after),
                ],
                'state' => $this->state($attempt),
            ];
        });
    }

    /** Цель (null — «пока не знаю») → итог; прохождение закончено. */
    public function finish(DiagnosticAttempt $attempt, ?int $target): void
    {
        if ($attempt->isFinished() || $this->currentQuestion($attempt) !== null) {
            return;
        }

        $high = [];
        $low = [];
        $accuracy = [];
        foreach (array_keys(config('diagnostic.blocks')) as $block) {
            $answers = $this->blockAnswers($attempt, $block);
            $high[$block] = BlockRun::result($answers);
            $low[$block] = BlockRun::result($answers, true);
            $accuracy[$block] = BlockRun::accuracy($answers);
        }

        $attempt->forceFill([
            'target_score' => $target,
            'target_set_at' => now(),
            'levels' => $high,
            'levels_low' => $low,
            'result' => $this->model->compute($high, $low) + [
                'guessed' => DiagnosticAnswer::where('diagnostic_attempt_id', $attempt->id)->where('correct', true)->where('guess', true)->count(),
                // Сколько раз нажал «Не знаю» (guess = пропуск) — для карточки админу.
                'skipped' => DiagnosticAnswer::where('diagnostic_attempt_id', $attempt->id)->where('guess', true)->count(),
                'accuracy' => $accuracy,
            ],
            'status' => DiagnosticAttempt::STATUS_FINISHED,
            'finished_at' => now(),
        ])->save();
    }

    /**
     * «Начать заново» посреди теста: незаконченное прохождение закрывается
     * (abandoned — в статистике видно, что бросил), начинается новое.
     * Законченное не трогаем — его итог остаётся в истории.
     */
    public function restart(DiagnosticAttempt $attempt, ?string $platform = null): DiagnosticAttempt
    {
        if (! $attempt->isFinished()) {
            $attempt->forceFill(['status' => DiagnosticAttempt::STATUS_ABANDONED, 'current_question_id' => null])->save();
        }

        return $this->start($attempt->contact, $attempt->entry ?? 'anonymous', $platform ?? $attempt->platform);
    }

    /** «Хочешь улучшить свои знания?» — ответ один на прохождение, второй источник ничего не меняет. */
    public function improve(DiagnosticAttempt $attempt, bool $yes, string $source): bool
    {
        $updated = DiagnosticAttempt::whereKey($attempt->id)
            ->where('status', DiagnosticAttempt::STATUS_FINISHED)
            ->whereNull('improve')
            ->update(['improve' => $yes ? 'yes' : 'no', 'improve_source' => $source, 'improve_at' => now()]);
        $attempt->refresh();

        return $updated > 0;
    }

    /**
     * Текущий вопрос; нет выданного — выдаёт следующий (и переходит к
     * следующему блоку, если этот кончился). null — все блоки пройдены.
     */
    private function currentQuestion(DiagnosticAttempt $attempt): ?array
    {
        if ($attempt->current_question_id && ($q = $this->bank->get($attempt->current_question_id))) {
            return $q;
        }

        while (($block = $attempt->currentBlock()) !== null) {
            $level = BlockRun::nextLevel($this->blockAnswers($attempt, $block));
            $question = $level === null ? null : $this->bank->pick($block, $level, $this->usedIds($attempt), $this->seenIds($attempt));
            if ($question !== null) {
                $attempt->forceFill(['current_question_id' => $question['id']])->save();

                return $question;
            }
            if ($level !== null) {
                // Банк кончился раньше пути — diagnostic:validate такого не пропускает.
                Log::warning("Диагностика: нет вопроса уровня {$level} в блоке {$block}", ['attempt' => $attempt->id]);
            }
            $attempt->block_index++;
            $attempt->save();
        }

        return null;
    }

    private function explainBy(array $question): ?string
    {
        if (($question['skin'] ?? null) === 'who') {
            foreach ($question['speakers'] ?? [] as $speaker) {
                if ((string) $speaker['id'] === (string) $question['correct']) {
                    return $speaker['who'] ?? null;
                }
            }
        }

        return $question['explain_by'] ?? null;
    }

    /** @return list<array{level: int, correct: bool, guess: bool}> */
    private function blockAnswers(DiagnosticAttempt $attempt, string $block): array
    {
        return DiagnosticAnswer::where('diagnostic_attempt_id', $attempt->id)->where('block', $block)
            ->orderBy('id')->get(['level', 'correct', 'guess'])
            ->map(fn ($a) => ['level' => (int) $a->level, 'correct' => (bool) $a->correct, 'guess' => (bool) $a->guess])
            ->all();
    }

    /** @return string[] */
    private function usedIds(DiagnosticAttempt $attempt): array
    {
        // По порядку ответов: последний — для «не та же механика, что в прошлом вопросе».
        return DiagnosticAnswer::where('diagnostic_attempt_id', $attempt->id)->orderBy('id')->pluck('question_id')->all();
    }

    /** Вопросы, которые этот человек видел в прошлых прохождениях. @return string[] */
    private function seenIds(DiagnosticAttempt $attempt): array
    {
        if (! $attempt->telegram_contact_id) {
            return [];
        }

        return DiagnosticAnswer::whereIn('diagnostic_attempt_id', DiagnosticAttempt::where('telegram_contact_id', $attempt->telegram_contact_id)
            ->where('id', '!=', $attempt->id)->select('id'))->distinct()->pluck('question_id')->all();
    }

    private function progress(DiagnosticAttempt $attempt): array
    {
        $blocks = config('diagnostic.blocks');
        $current = $attempt->block_order[$attempt->block_index] ?? null;

        return [
            'index' => min($attempt->block_index, count($attempt->block_order)),
            'total' => count($attempt->block_order),
            // Отвечено в текущем блоке (из BlockRun::MAX_QUESTIONS) — заливка текущего отрезка.
            'answered' => $current ? count($this->blockAnswers($attempt, $current)) : 0,
            'blocks' => array_map(fn ($key) => ['key' => $key, 'title' => $blocks[$key]['title']], $attempt->block_order),
        ];
    }

    private function resultView(DiagnosticAttempt $attempt): array
    {
        $r = $attempt->result;
        $blocks = config('diagnostic.blocks');
        $mid = (int) round(($r['low'] + $r['high']) / 2);

        return [
            'low' => $r['low'],
            'high' => $r['high'],
            'potential' => $r['potential'],
            'target' => $attempt->target_score,
            'to_target' => $attempt->target_score !== null ? max(0, $attempt->target_score - $mid) : null,
            'near_target' => $attempt->target_score !== null && $r['high'] >= $attempt->target_score,
            'gains' => array_values(array_map(
                // accuracy — для профиля; у прохождений до неё — по уровню.
                fn ($key) => ['key' => $key, 'title' => $blocks[$key]['title'], 'gain' => $r['gains'][$key], 'level' => $attempt->levels[$key] ?? 0,
                    'accuracy' => $r['accuracy'][$key] ?? [0.1, 0.35, 0.65, 0.9][$attempt->levels[$key] ?? 0]],
                array_keys($r['gains']),
            )),
            'weak' => array_map(fn ($key) => $blocks[$key]['title'], $r['weak']),
            'weak_acc' => array_map(fn ($key) => $blocks[$key]['accusative'] ?? $blocks[$key]['title'], $r['weak']),
            'guessed' => $r['guessed'] ?? 0,
        ];
    }
}
