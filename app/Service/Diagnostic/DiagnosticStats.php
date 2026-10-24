<?php

namespace App\Service\Diagnostic;

use App\Models\DiagnosticAnswer;
use App\Models\DiagnosticAttempt;
use App\Models\TelegramContact;
use App\Service\Telegram\TelegramBots;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Цифры диагностики для админки (/admin/telegram/diagnostic, ТЗ — раздел
 * 8.2): воронка по контактам бота diag, результаты и статистика по
 * вопросам — по ней через 200–300 прохождений калибруется сложность. Только
 * читает. Период — по дате запуска бота (как у воронки урока). Анонимные
 * прохождения (без контакта, разработка) не считаются.
 */
final class DiagnosticStats
{
    /** Шаги воронки по порядку: ключ → подпись. */
    public const STEPS = [
        'starts' => 'Запустили бота',
        'opened' => 'Открыли тест',
        'graded' => 'Ответили на класс',
        'finished' => 'Прошли до конца',
        'yes' => '«Да, хочу»',
        'no' => '«Пока нет»',
        'wrote' => 'Написали в диалог',
        'blocked' => 'Заблокировали',
    ];

    public function __construct(private readonly ?CarbonInterface $from = null, private readonly ?CarbonInterface $to = null)
    {
    }

    /** @return Collection<int, TelegramContact> контакты периода с прохождениями */
    public function contacts(): Collection
    {
        return TelegramContact::forBot(TelegramBots::DIAG)
            ->when($this->from, fn ($q) => $q->where('started_at', '>=', $this->from->copy()->startOfDay()))
            ->when($this->to, fn ($q) => $q->where('started_at', '<=', $this->to->copy()->endOfDay()))
            ->with('diagnosticAttempts')
            ->get();
    }

    /** @return array<string, int> шаг → сколько человек */
    public static function funnel(Collection $contacts): array
    {
        $count = fn (callable $has) => $contacts->filter($has)->count();
        $any = fn (TelegramContact $c, callable $attempt) => $c->diagnosticAttempts->contains($attempt);

        return [
            'starts' => $contacts->count(),
            'opened' => $count(fn ($c) => $c->diagnosticAttempts->isNotEmpty()),
            'graded' => $count(fn ($c) => $any($c, fn ($a) => $a->grade !== null)),
            'finished' => $count(fn ($c) => $any($c, fn ($a) => $a->isFinished())),
            'yes' => $count(fn ($c) => $any($c, fn ($a) => $a->improve === 'yes')),
            'no' => $count(fn ($c) => $any($c, fn ($a) => $a->improve === 'no')),
            'wrote' => $count(fn ($c) => $c->first_user_message_at !== null),
            'blocked' => $count(fn ($c) => $c->blocked_at !== null),
        ];
    }

    /** @return array<string, array<string, int>> метка → воронка, по убыванию стартов */
    public static function bySource(Collection $contacts): array
    {
        return $contacts->groupBy(fn ($c) => $c->source ?? '')
            ->map(fn ($group) => self::funnel($group))
            ->sortByDesc('starts')
            ->all();
    }

    /** Последнее законченное прохождение каждого контакта. @return Collection<int, DiagnosticAttempt> */
    public static function finished(Collection $contacts): Collection
    {
        return $contacts->map(fn ($c) => $c->diagnosticAttempts->filter->isFinished()->sortByDesc('id')->first())->filter()->values();
    }

    /**
     * @return array{count: int, avg: ?int, grades: array<string, int>, levels: array<string, array<int, int>>}
     */
    public static function results(Collection $finished): array
    {
        $levels = [];
        foreach (array_keys(config('diagnostic.blocks')) as $block) {
            $levels[$block] = [0 => 0, 1 => 0, 2 => 0, 3 => 0];
            foreach ($finished as $a) {
                $levels[$block][(int) ($a->levels[$block] ?? 0)]++;
            }
        }

        return [
            'count' => $finished->count(),
            'avg' => $finished->isEmpty() ? null : (int) round($finished->avg(fn ($a) => ($a->result['low'] + $a->result['high']) / 2)),
            'grades' => $finished->countBy(fn ($a) => $a->grade ?? '—')->all(),
            'levels' => $levels,
        ];
    }

    /**
     * По вопросам банка: сколько раз задан, доля верных и «Наугад», среднее
     * время ответа — то, чем потом калибруется реальная сложность.
     *
     * @return list<array{id: string, prompt: string, block: string, level: int, mechanic: string, shown: int, correct: int, guess: int, ms: ?int}>
     */
    public function questions(Collection $contacts): array
    {
        $attemptIds = $contacts->flatMap(fn ($c) => $c->diagnosticAttempts->pluck('id'))->all();
        if ($attemptIds === []) {
            return [];
        }

        $bank = QuestionBank::fromConfig();
        $rows = DiagnosticAnswer::whereIn('diagnostic_attempt_id', $attemptIds)
            ->selectRaw('question_id, block, level, mechanic, COUNT(*) as shown, SUM(correct) as correct_n, SUM(guess) as guess_n, AVG(answer_ms) as ms')
            ->groupBy('question_id', 'block', 'level', 'mechanic')
            ->get();

        return $rows->map(fn ($r) => [
            'id' => $r->question_id,
            'prompt' => mb_strimwidth((string) ($bank->get($r->question_id)['prompt'] ?? '— нет в банке —'), 0, 90, '…'),
            'block' => $r->block,
            'level' => (int) $r->level,
            'mechanic' => $r->mechanic,
            'shown' => (int) $r->shown,
            'correct' => (int) round($r->correct_n / max(1, $r->shown) * 100),
            'guess' => (int) round($r->guess_n / max(1, $r->shown) * 100),
            'ms' => $r->ms !== null ? (int) round($r->ms) : null,
        ])->sortBy([['block', 'asc'], ['level', 'asc'], ['id', 'asc']])->values()->all();
    }
}
