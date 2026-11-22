<?php

namespace App\Service\Statements;

use App\Models\Statement;
use App\Models\StatementAnswer;
use App\Models\StatementSession;
use App\Models\User;
use App\Models\UserStatement;
use App\Service\FishFoodService;
use App\Service\Terms\SessionReward;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * «Верные суждения»: сессии, ответы, повтор ошибок, корм. Механика —
 * docs/statements/Верные суждения — механика.md. Чистая логика — в
 * StatementSchedule (когда повторять) и StatementPicker (что показать).
 */
class StatementTrainer
{
    public const ANSWERS = ['true', 'false', 'unknown'];

    private StatementSchedule $schedule;

    private SessionReward $reward;

    private StatementPicker $picker;

    public function __construct(private FishFoodService $fish, ?StatementPicker $picker = null)
    {
        $this->schedule = StatementSchedule::fromConfig();
        $this->reward = new SessionReward(config('statements.corm'));
        $this->picker = $picker ?? new StatementPicker();
    }

    public function today(): string
    {
        return now()->toDateString();
    }

    /*
    |--------------------------------------------------------------------------
    | Обзор (главная тренажёра, рыба, зал)
    |--------------------------------------------------------------------------
    */

    /**
     * @return array{blocks: array, due: int, overdue: int, due_tomorrow: int, unseen: int,
     *   finished_today: int, unfinished: ?StatementSession}
     *   blocks — [key => {title, total, seen, known, due}]
     */
    public function overview(User $user): array
    {
        $today = $this->today();
        $totals = Statement::active()->selectRaw('block, count(*) as n')->groupBy('block')->pluck('n', 'block');

        $progress = UserStatement::query()
            ->join('statements', 'statements.id', '=', 'user_statements.statement_id')
            ->where('user_statements.user_id', $user->id)
            ->where('statements.is_active', true)
            ->get(['statements.block', 'user_statements.due_on', 'user_statements.last_correct']);

        $blocks = [];
        foreach (config('statements.blocks') as $key => $title) {
            $rows = $progress->where('block', $key);
            $blocks[$key] = [
                'title' => $title,
                'total' => (int) ($totals[$key] ?? 0),
                'seen' => $rows->count(),
                'known' => $rows->where('last_correct', true)->count(),
                'due' => $rows->filter(fn ($r) => $r->due_on && $r->due_on->toDateString() <= $today)->count(),
            ];
        }

        $tomorrow = Carbon::parse($today)->addDay()->toDateString();

        return [
            'blocks' => $blocks,
            'due' => array_sum(array_column($blocks, 'due')),
            'overdue' => $progress->filter(fn ($r) => $r->due_on && $r->due_on->toDateString() < $today)->count(),
            'due_tomorrow' => $progress->filter(fn ($r) => $r->due_on && $r->due_on->toDateString() === $tomorrow)->count(),
            'unseen' => array_sum(array_column($blocks, 'total')) - array_sum(array_column($blocks, 'seen')),
            'finished_today' => StatementSession::query()
                ->where('user_id', $user->id)
                ->where('kind', StatementSession::KIND_REGULAR)
                ->where('status', StatementSession::FINISHED)
                ->whereDate('day', $today)
                ->count(),
            'unfinished' => $this->unfinished($user),
            'corm' => $this->cormToday((int) $user->id),
        ];
    }

    public function unfinished(User $user): ?StatementSession
    {
        return StatementSession::query()
            ->where('user_id', $user->id)
            ->where('status', StatementSession::IN_PROGRESS)
            ->latest('id')
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Старт
    |--------------------------------------------------------------------------
    */

    /**
     * Новая сессия по блокам. null — в этих блоках нет ни одного суждения.
     * Незаконченная прошлая сессия закрывается (abandoned).
     *
     * @param  string[]  $blocks
     */
    public function start(User $user, array $blocks): ?StatementSession
    {
        $blocks = array_values(array_intersect(array_keys(config('statements.blocks')), $blocks));
        if (! $blocks) {
            return null;
        }

        return DB::transaction(function () use ($user, $blocks) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $today = $this->today();
            $size = $this->planCap($user, 'session', (int) config('statements.session_size'));

            $candidate = fn ($q) => $q->get(['statements.id', 'statements.is_true', 'statements.source_task_id'])
                ->map(fn ($s) => ['id' => (int) $s->id, 'is_true' => (bool) $s->is_true, 'task' => $s->source_task_id])
                ->all();

            $mine = fn () => Statement::active()
                ->whereIn('statements.block', $blocks)
                ->join('user_statements', function ($join) use ($user) {
                    $join->on('user_statements.statement_id', '=', 'statements.id')
                        ->where('user_statements.user_id', $user->id);
                });

            $due = $candidate($mine()->whereDate('user_statements.due_on', '<=', $today)->orderBy('user_statements.due_on'));
            $seen = $candidate($mine()
                ->where(fn ($q) => $q->whereNull('user_statements.due_on')->orWhereDate('user_statements.due_on', '>', $today))
                ->orderBy('user_statements.last_answered_at')
                ->limit($size * 4));
            $unseen = $candidate(Statement::active()
                ->whereIn('statements.block', $blocks)
                ->whereNotExists(function ($q) use ($user) {
                    $q->selectRaw('1')->from('user_statements')
                        ->whereColumn('user_statements.statement_id', 'statements.id')
                        ->where('user_statements.user_id', $user->id);
                })
                ->inRandomOrder()
                ->limit($size * 4));

            $ids = $this->picker->pick($due, $unseen, $seen, $size);
            if (! $ids) {
                return null;
            }

            $this->abandonOpen($user);

            return StatementSession::create([
                'user_id' => $user->id,
                'kind' => StatementSession::KIND_REGULAR,
                'blocks' => $blocks,
                'items' => $this->withCharacters($ids),
                'results' => [],
                'total' => count($ids),
                'day' => $today,
            ]);
        });
    }

    /**
     * «Повторить ошибки» законченной сессии: те же карточки, где была ошибка
     * или «Не знаю». Расписание не трогает, корма не даёт. null — ошибок нет.
     */
    public function startMistakes(User $user, StatementSession $parent): ?StatementSession
    {
        if ((int) $parent->user_id !== (int) $user->id || ! $parent->isFinished()) {
            return null;
        }

        $ids = [];
        foreach ($parent->items as $i => $item) {
            if (! ($parent->results[$i]['correct'] ?? false)) {
                $ids[] = (int) $item['id'];
            }
        }
        if (! $ids) {
            return null;
        }
        shuffle($ids);

        return DB::transaction(function () use ($user, $parent, $ids) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $this->abandonOpen($user);

            return StatementSession::create([
                'user_id' => $user->id,
                'kind' => StatementSession::KIND_MISTAKES,
                'blocks' => $parent->blocks,
                'items' => $this->withCharacters($ids),
                'results' => [],
                'total' => count($ids),
                'day' => $this->today(),
                'parent_id' => $parent->id,
            ]);
        });
    }

    private function abandonOpen(User $user): void
    {
        StatementSession::query()
            ->where('user_id', $user->id)
            ->where('status', StatementSession::IN_PROGRESS)
            ->update(['status' => StatementSession::ABANDONED, 'updated_at' => now()]);
    }

    /** Каждой карточке — случайный персонаж и его вступление. */
    private function withCharacters(array $ids): array
    {
        $characters = config('statements.characters');

        return array_map(function (int $id) use ($characters) {
            $who = array_rand($characters);

            return ['id' => $id, 'who' => $who, 'intro' => Arr::random($characters[$who]['intro'])];
        }, $ids);
    }

    /*
    |--------------------------------------------------------------------------
    | Экран сессии
    |--------------------------------------------------------------------------
    */

    /**
     * Карточки для страницы — без ответов (ответ отдаёт только answer()).
     *
     * @return array<int, array{position: int, block: string, about: ?string, text: string, who: string, intro: string}>
     */
    public function cards(StatementSession $session): array
    {
        $statements = Statement::whereIn('id', array_column($session->items, 'id'))->get()->keyBy('id');
        $cards = [];
        foreach ($session->items as $i => $item) {
            $s = $statements[$item['id']] ?? null;
            $cards[] = [
                'position' => $i,
                'block' => $s?->block,
                'about' => $s?->about,
                'text' => $s?->text ?? '',
                'who' => $item['who'],
                'intro' => $item['intro'],
            ];
        }

        return $cards;
    }

    /*
    |--------------------------------------------------------------------------
    | Ответ
    |--------------------------------------------------------------------------
    */

    /**
     * Ответ на карточку position. Повтор того же запроса (двойной клик,
     * повтор после обрыва сети) ничего не меняет и отдаёт тот же разбор.
     *
     * @return array{result: array, finished: bool, summary: ?array}|null
     *                                                                    null — позиция не текущая (страница устарела)
     */
    public function answer(StatementSession $session, int $position, string $answer, ?int $ms = null): ?array
    {
        return DB::transaction(function () use ($session, $position, $answer, $ms) {
            $locked = StatementSession::whereKey($session->id)->lockForUpdate()->first();
            $results = $locked->results ?? [];

            if (isset($results[$position])) {
                return $this->replay($locked, $position);
            }
            if ($locked->status !== StatementSession::IN_PROGRESS || $position !== (int) $locked->position) {
                return null;
            }

            $item = $locked->items[$position];
            $statement = Statement::findOrFail($item['id']);
            $correct = match ($answer) {
                'true' => $statement->is_true,
                'false' => ! $statement->is_true,
                default => false,
            };

            // Прогресс пишется всегда; не «$correct && record…»: с коротким
            // замыканием ошибки не попадали бы на повтор.
            $wasWrong = $this->recordProgress($locked, $statement, $correct);
            $rightAgain = $correct && $wasWrong;

            StatementAnswer::create([
                'session_id' => $locked->id,
                'user_id' => $locked->user_id,
                'statement_id' => $statement->id,
                'position' => $position,
                'answer' => $answer,
                'correct' => $correct,
                'who' => $item['who'],
                'ms' => $ms !== null ? max(0, min($ms, 3_600_000)) : null,
            ]);

            $results[$position] = ['answer' => $answer, 'correct' => $correct, 'again' => $rightAgain];
            $locked->results = $results;
            $locked->position = $position + 1;
            $locked->correct = (int) $locked->correct + ($correct ? 1 : 0);
            $locked->streak = $correct ? (int) $locked->streak + 1 : 0;
            $locked->best_streak = max((int) $locked->best_streak, (int) $locked->streak);

            if ($locked->position >= $locked->total) {
                $this->finish($locked);
            }
            $locked->save();

            return $this->replay($locked, $position, (int) $locked->streak);
        });
    }

    /**
     * Прогресс суждения (только обычная сессия). true — раньше по этому
     * суждению была ошибка, а сейчас верно («Камбэк засчитан»).
     */
    private function recordProgress(StatementSession $session, Statement $statement, bool $correct): bool
    {
        $row = UserStatement::where('user_id', $session->user_id)
            ->where('statement_id', $statement->id)
            ->lockForUpdate()
            ->first();
        $wasWrong = $row !== null && ! $row->last_correct;

        if ($session->kind !== StatementSession::KIND_REGULAR) {
            return $wasWrong;
        }

        $next = $this->schedule->after(
            $row?->step,
            $row?->due_on?->toDateString(),
            $correct,
            $this->today()
        );

        $row ??= new UserStatement(['user_id' => $session->user_id, 'statement_id' => $statement->id]);
        $row->step = $next['step'];
        $row->due_on = $next['due_on'];
        $row->right_count = (int) $row->right_count + ($correct ? 1 : 0);
        $row->wrong_count = (int) $row->wrong_count + ($correct ? 0 : 1);
        $row->last_correct = $correct;
        $row->last_answered_at = now();
        $row->save();

        return $wasWrong;
    }

    private function finish(StatementSession $session): void
    {
        $session->status = StatementSession::FINISHED;
        $session->finished_at = now();

        if ($session->kind !== StatementSession::KIND_REGULAR || $session->day->toDateString() !== $this->today()) {
            return;
        }

        if ($this->cormToday((int) $session->user_id)['left'] <= 0) {
            return;
        }

        $corm = $this->reward->corm((int) $session->correct, (int) $session->total);
        // Корм общий для всех тренажёров — то же начисление, что у терминов.
        $this->fish->awardTermSession(User::find($session->user_id), $corm);
        $session->corm_awarded = $corm;
    }

    /**
     * Корм за сегодня. В лимит идут только сессии, которые принесли корм:
     * сессия с плохим счётом (0 🍔) попытку «с кормом» не тратит.
     *
     * @return array{limit: int, paid: int, left: int, earned: int}
     */
    public function cormToday(int $userId): array
    {
        $paid = StatementSession::query()
            ->where('user_id', $userId)
            ->where('kind', StatementSession::KIND_REGULAR)
            ->whereDate('day', $this->today())
            ->where('corm_awarded', '>', 0)
            ->get(['corm_awarded']);
        $limit = (int) config('statements.corm_sessions_per_day');

        return [
            'limit' => $limit,
            'paid' => $paid->count(),
            'left' => max(0, $limit - $paid->count()),
            'earned' => (int) $paid->sum('corm_awarded'),
        ];
    }

    /** «2 раунда» — число со словом для фраз про корм (ученику сессия — «раунд»). */
    public static function rounds(int $n): string
    {
        return \App\Support\Text::plural($n, ['раунд', 'раунда', 'раундов']);
    }

    /** Сколько верных из $total нужно для корма (нижний порог config statements.corm). */
    public function cormMinCorrect(int $total): int
    {
        $percent = min(array_keys(config('statements.corm')));

        return (int) ceil($total * $percent / 100);
    }

    /**
     * Строка про корм под итогом сессии: сколько ещё сессий с кормом или
     * почему корма не было. null — для «Повторить ошибки» (там корма нет
     * по правилам, и это написано на кнопке).
     */
    private function cormNote(StatementSession $session): ?string
    {
        if ($session->kind !== StatementSession::KIND_REGULAR) {
            return null;
        }

        $phrases = config('statements.phrases');
        if ($session->day->toDateString() !== $this->today()) {
            return null;
        }

        $today = $this->cormToday((int) $session->user_id);
        if ((int) $session->corm_awarded > 0) {
            return $today['left'] > 0
                ? strtr($phrases['corm_after_left'], [':rounds' => self::rounds($today['left'])])
                : $phrases['corm_after_last'];
        }

        // Корма не было: либо лимит уже был выбран, либо мало верных.
        $wouldPay = $this->reward->corm((int) $session->correct, (int) $session->total) > 0;

        return $wouldPay
            ? $phrases['corm_after_limit']
            : strtr($phrases['corm_after_score'], [':min' => $this->cormMinCorrect((int) $session->total), ':total' => $session->total]);
    }

    /** Разбор карточки position (и итог, если сессия закончена). */
    private function replay(StatementSession $session, int $position, ?int $streak = null): array
    {
        $item = $session->items[$position];
        $res = $session->results[$position];
        $statement = Statement::find($item['id']);
        $phrases = config('statements.phrases');
        $character = config('statements.characters.'.$item['who']);

        $say = match (true) {
            $res['correct'] && ! empty($res['again']) => $phrases['right_again'],
            $res['correct'] => $phrases['right'],
            $res['answer'] === 'unknown' => $phrases['unknown'],
            $statement->is_true => $phrases['wrong_true'],
            default => $phrases['wrong_false'],
        };

        $combo = null;
        if ($streak !== null && isset($phrases['combo'][$streak])) {
            $combo = Arr::random($phrases['combo'][$streak]);
        }

        return [
            'result' => [
                'position' => $position,
                'answer' => $res['answer'],
                'correct' => (bool) $res['correct'],
                'is_true' => $statement->is_true,
                'say' => Arr::random($say),
                'explanation' => $statement->explanation,
                'correction' => $statement->is_true ? null : $statement->correction,
                'reaction' => Arr::random($character[$statement->is_true ? 'right' : 'wrong']),
                'combo' => $combo,
                'streak' => $streak,
                'statement_id' => $statement->id,
            ],
            'finished' => $session->isFinished(),
            'summary' => $session->isFinished() ? $this->summary($session) : null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Итог
    |--------------------------------------------------------------------------
    */

    public function summary(StatementSession $session): array
    {
        $statements = Statement::whereIn('id', array_column($session->items, 'id'))->get()->keyBy('id');
        $titles = config('statements.blocks');
        $blocks = [];
        $mistakes = [];

        foreach ($session->items as $i => $item) {
            $s = $statements[$item['id']] ?? null;
            if (! $s) {
                continue;
            }
            $correct = (bool) ($session->results[$i]['correct'] ?? false);
            $blocks[$s->block] ??= [
                'key' => $s->block,
                'title' => $titles[$s->block] ?? $s->block,
                'emoji' => config('statements.block_emoji.'.$s->block),
                'total' => 0,
                'correct' => 0,
            ];
            $blocks[$s->block]['total']++;
            $blocks[$s->block]['correct'] += $correct ? 1 : 0;

            if (! $correct) {
                $mistakes[] = [
                    'about' => $s->about,
                    'text' => $s->text,
                    'is_true' => $s->is_true,
                    'answer' => $session->results[$i]['answer'] ?? null,
                    'explanation' => $s->explanation,
                    'correction' => $s->is_true ? null : $s->correction,
                ];
            }
        }

        $total = (int) $session->total;
        $correct = (int) $session->correct;
        $key = match (true) {
            $session->kind === StatementSession::KIND_MISTAKES => 'result_mistakes',
            $correct === $total => 'result_perfect',
            $correct * 3 >= $total * 2 => 'result_good',
            default => 'result_hard',
        };

        // Сильный блок — без ошибок, точка роста — меньше двух третей верных.
        $strong = $growth = [];
        foreach ($blocks as $b) {
            if ($b['correct'] === $b['total']) {
                $strong[] = $b['title'];
            } elseif ($b['correct'] * 3 < $b['total'] * 2) {
                $growth[] = $b['title'];
            }
        }

        return [
            'kind' => $session->kind,
            'total' => $total,
            'correct' => $correct,
            'best_streak' => (int) $session->best_streak,
            'corm' => (int) $session->corm_awarded,
            'corm_note' => $this->cormNote($session),
            'say' => Arr::random(config('statements.phrases.'.$key)),
            'blocks' => array_values($blocks),
            'strong' => $strong,
            'growth' => $growth,
            'mistakes' => $mistakes,
        ];
    }

    /**
     * Потолок сессии этого вида: пока тренажёр в «Плане на сегодня» и пункт
     * не сделан — квота плана (App\Service\Trainers\DailyPlanService).
     */
    private function planCap(User $user, string $kind, int $limit): int
    {
        $quota = app(\App\Service\Trainers\DailyPlanService::class)->quota($user, \App\Service\Trainers\StatementsTrainerCard::class, $kind);

        return $quota === null ? $limit : min($limit, $quota);
    }
}
