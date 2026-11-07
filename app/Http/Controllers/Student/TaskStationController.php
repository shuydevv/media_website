<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskReport;
use App\Models\TaskReview;
use App\Models\TaskRound;
use App\Service\Bank\TaskTrainer;
use App\Service\Bank\WrittenPractice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Станция «Задания» (docs/bank/Задания — механика.md). Главная — выбор
 * части, номеров и блоков; раунд первой части — одна страница со скриптом
 * (ответы — JSON, проверку и разбор считает сервер); подход второй части —
 * страница с ответом, сверкой с образцом и отправкой куратору.
 */
class TaskStationController extends Controller
{
    public function __construct(private TaskTrainer $trainer, private WrittenPractice $written)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $written = $this->written->overview($user);
        $part = $request->query('part') === '2' ? 2 : 1;

        return view('student.ege.index', [
            'part' => $part,
            'overview' => $this->trainer->overview($user),
            'written' => $written,
            'cormBalance' => (int) $user->fish_corm_balance,
            'blocks' => config('statements.blocks'),
            'blockEmoji' => config('statements.block_emoji'),
            'texts' => config('task_trainer.texts'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Часть 1
    |--------------------------------------------------------------------------
    */

    public function start(Request $request)
    {
        $data = $request->validate([
            'numbers' => ['required_without:blocks', 'array', 'min:1'],
            'numbers.*' => ['integer', Rule::in(TaskTrainer::part1Numbers())],
            'blocks' => ['nullable', 'array'],
            'blocks.*' => ['string', Rule::in(array_keys(config('statements.blocks')))],
        ], ['numbers.required_without' => config('task_trainer.texts.start_empty')]);

        // Либо номера, либо блоки: выбран блок — номера все (1–16).
        $blocks = $data['blocks'] ?? [];
        $numbers = $blocks ? TaskTrainer::part1Numbers() : $data['numbers'];

        $round = $this->trainer->start($request->user(), $numbers, $blocks);
        if (! $round) {
            return redirect()->route('student.ege.index')->with('ege_empty', config('task_trainer.texts.none_found'));
        }

        return redirect()->route('student.ege.round', $round);
    }

    public function round(Request $request, TaskRound $taskRound)
    {
        $this->own($request, $taskRound->user_id);
        if ($taskRound->status === TaskRound::ABANDONED) {
            return redirect()->route('student.ege.index');
        }

        return view('student.ege.round', [
            'round' => $taskRound,
            'boot' => [
                'answerUrl' => route('student.ege.round.answer', $taskRound),
                'reportUrl' => route('student.ege.report'),
                'mistakesUrl' => route('student.ege.mistakes', $taskRound),
                'startUrl' => route('student.ege.start'),
                'indexUrl' => route('student.ege.index'),
                'hallUrl' => route('student.trainers.index', ['hall' => 'social']),
                'kind' => $taskRound->kind,
                'numbers' => $taskRound->numbers,
                'blocks' => $taskRound->blocks,
                'position' => (int) $taskRound->position,
                'total' => (int) $taskRound->total,
                'cards' => $this->trainer->cards($taskRound),
                'pending' => $this->trainer->pendingNow($taskRound),
                'mascot' => asset('img/diagnostic/squid'),
                'mascotName' => config('task_trainer.mascot.name'),
                'foodIcon' => $request->user()->fish_food_icon_url,
                'texts' => config('task_trainer.texts'),
                'combo' => config('task_trainer.mascot.combo'),
                'summary' => $taskRound->isFinished() ? $this->trainer->summary($taskRound) : null,
            ],
        ]);
    }

    public function answer(Request $request, TaskRound $taskRound)
    {
        $this->own($request, $taskRound->user_id);
        $data = $request->validate([
            'position' => ['required', 'integer', 'min:0'],
            'answer' => ['nullable', 'string', 'max:40'],
            'ms' => ['nullable', 'integer', 'min:0'],
            'mode' => ['nullable', 'in:check,retry,reveal'],
        ]);

        $result = $this->trainer->answer($taskRound, (int) $data['position'], $data['answer'] ?? '', isset($data['ms']) ? (int) $data['ms'] : null, $data['mode'] ?? TaskTrainer::CHECK);
        if ($result === null) {
            return response()->json(['stale' => true], 409);
        }

        return response()->json($result);
    }

    public function mistakes(Request $request, TaskRound $taskRound)
    {
        $this->own($request, $taskRound->user_id);
        $round = $this->trainer->startMistakes($request->user(), $taskRound);

        return $round
            ? redirect()->route('student.ege.round', $round)
            : redirect()->route('student.ege.index');
    }

    public function report(Request $request)
    {
        $data = $request->validate([
            'task_id' => ['required', 'integer'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);
        $user = $request->user();
        $id = (int) $data['task_id'];

        // Жаловаться можно только на задание, которое ученику выдавали.
        $own = TaskRound::where('user_id', $user->id)->latest('id')->limit(30)->pluck('items')->flatten()->map(fn ($v) => (int) $v)->contains($id)
            || TaskReview::where('user_id', $user->id)->latest('id')->limit(30)->pluck('items')->flatten()->map(fn ($v) => (int) $v)->contains($id);
        abort_unless($own, 422);

        TaskReport::create([
            'user_id' => $user->id,
            'task_id' => Task::findOrFail($id)->id,
            'message' => $data['message'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    /*
    |--------------------------------------------------------------------------
    | Часть 2
    |--------------------------------------------------------------------------
    */

    public function writtenStart(Request $request)
    {
        $data = $request->validate([
            'number' => ['required', 'integer', Rule::in(TaskTrainer::part2Numbers())],
            'blocks' => ['nullable', 'array'],
            'blocks.*' => ['string', Rule::in(array_keys(config('statements.blocks')))],
        ], ['number.required' => config('task_trainer.texts.start_part2_empty')]);

        $review = $this->written->start($request->user(), (int) $data['number'], $data['blocks'] ?? []);
        if (! $review) {
            return redirect()->route('student.ege.index', ['part' => 2])->with('ege_empty', config('task_trainer.texts.none_found'));
        }

        return redirect()->route('student.ege.written', $review);
    }

    public function written(Request $request, TaskReview $taskReview)
    {
        $this->own($request, $taskReview->user_id);
        $this->written->markSeen($taskReview);
        $tasks = $taskReview->tasks();

        return view('student.ege.written', [
            'review' => $taskReview,
            'tasks' => $tasks,
            'texts' => config('task_trainer.texts'),
            'checklists' => $tasks->mapWithKeys(fn (Task $t) => [$t->id => config('task_selfcheck.'.(int) $t->number)]),
            'sendsLeft' => $this->written->sendsLeft($request->user()),
            'numberTitle' => config('task_trainer.numbers.'.(int) $tasks->first()?->number),
        ]);
    }

    public function writtenDraft(Request $request, TaskReview $taskReview)
    {
        $this->own($request, $taskReview->user_id);
        $data = $request->validate(['answers' => ['nullable', 'array'], 'answers.*' => ['nullable', 'string', 'max:'.WrittenPractice::MAX_LENGTH]]);
        $this->written->saveDraft($taskReview, $data['answers'] ?? []);

        return response()->json(['ok' => true, 'status' => $taskReview->fresh()->status]);
    }

    public function writtenSelf(Request $request, TaskReview $taskReview)
    {
        $this->own($request, $taskReview->user_id);
        $data = $request->validate(['answers' => ['nullable', 'array'], 'answers.*' => ['nullable', 'string', 'max:'.WrittenPractice::MAX_LENGTH]]);
        $this->written->selfCheck($taskReview, $data['answers'] ?? []);

        return redirect()->route('student.ege.written', $taskReview);
    }

    public function writtenSend(Request $request, TaskReview $taskReview)
    {
        $this->own($request, $taskReview->user_id);
        $data = $request->validate(['answers' => ['nullable', 'array'], 'answers.*' => ['nullable', 'string', 'max:'.WrittenPractice::MAX_LENGTH]]);
        // Даже если отправка не прошла (лимит, короткий ответ) — черновик не теряем.
        $this->written->saveDraft($taskReview, $data['answers'] ?? []);
        $this->written->send($taskReview, $data['answers'] ?? []);

        return redirect()->route('student.ege.written', $taskReview)->with('ege_sent', true);
    }

    public function writtenMarks(Request $request, TaskReview $taskReview)
    {
        $this->own($request, $taskReview->user_id);
        $data = $request->validate([
            'marks' => ['required', 'array'],
            'marks.*.items' => ['array'],
            'marks.*.items.*' => ['integer', 'min:0', 'max:6'],
            'marks.*.penalty' => ['nullable', 'integer', 'min:0', 'max:2'],
        ]);
        $this->written->saveMarks($taskReview, $data['marks']);
        $fresh = $taskReview->fresh();

        return response()->json(['self_score' => $fresh->self_score, 'max' => $fresh->max_score]);
    }

    private function own(Request $request, int $userId): void
    {
        abort_unless($userId === (int) $request->user()->id, 404);
    }
}
