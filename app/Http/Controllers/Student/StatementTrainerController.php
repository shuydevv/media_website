<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Statement;
use App\Models\StatementReport;
use App\Models\StatementSession;
use App\Service\Statements\StatementTrainer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Тренажёр «Верные суждения». Главная — выбор блоков, сессия — одна
 * страница со скриптом: карточки приходят с разметкой, ответы — JSON
 * (answer), разбор и итог считает сервер.
 */
class StatementTrainerController extends Controller
{
    public function __construct(private StatementTrainer $trainer)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        return view('student.statements.index', [
            'overview' => $this->trainer->overview($user),
            'cormBalance' => (int) $user->fish_corm_balance,
        ]);
    }

    public function start(Request $request)
    {
        $data = $request->validate([
            'blocks' => ['required', 'array', 'min:1'],
            'blocks.*' => ['string', Rule::in(array_keys(config('statements.blocks')))],
        ], [
            'blocks.required' => 'Выберите хотя бы один блок',
        ]);

        $session = $this->trainer->start($request->user(), $data['blocks']);
        if (! $session) {
            return redirect()->route('student.statements.index')
                ->with('statements_empty', config('statements.phrases.empty'));
        }

        return redirect()->route('student.statements.session', $session);
    }

    public function mistakes(Request $request, StatementSession $statementSession)
    {
        $this->own($request, $statementSession);
        $session = $this->trainer->startMistakes($request->user(), $statementSession);

        return $session
            ? redirect()->route('student.statements.session', $session)
            : redirect()->route('student.statements.index');
    }

    public function show(Request $request, StatementSession $statementSession)
    {
        $this->own($request, $statementSession);
        if ($statementSession->status === StatementSession::ABANDONED) {
            return redirect()->route('student.statements.index');
        }

        $characters = [];
        foreach (array_keys(config('statements.characters')) as $key) {
            $c = config('diagnostic.characters.'.$key);
            $characters[$key] = [
                'name' => $c['name'],
                'emoji' => $c['emoji'],
                'color' => $c['color'],
                'avatar' => $c['avatar'] ? asset($c['avatar']) : null,
            ];
        }

        return view('student.statements.session', [
            'session' => $statementSession,
            'boot' => [
                'answerUrl' => route('student.statements.session.answer', $statementSession),
                'reportUrl' => route('student.statements.session.report', $statementSession),
                'mistakesUrl' => route('student.statements.mistakes', $statementSession),
                'startUrl' => route('student.statements.start'),
                'indexUrl' => route('student.statements.index'),
                'hallUrl' => route('student.trainers.index', ['hall' => 'social']),
                'kind' => $statementSession->kind,
                'blocks' => $statementSession->blocks,
                'blockTitles' => config('statements.blocks'),
                'position' => (int) $statementSession->position,
                'total' => (int) $statementSession->total,
                'cards' => $this->trainer->cards($statementSession),
                'characters' => $characters,
                'shark' => asset('img/diagnostic/shark'),
                'foodIcon' => $request->user()->fish_food_icon_url,
                'correctionLabel' => config('statements.phrases.correction'),
                'mistakesReturn' => config('statements.phrases.mistakes_return'),
                'summary' => $statementSession->isFinished() ? $this->trainer->summary($statementSession) : null,
            ],
        ]);
    }

    public function answer(Request $request, StatementSession $statementSession)
    {
        $this->own($request, $statementSession);
        $data = $request->validate([
            'position' => ['required', 'integer', 'min:0'],
            'answer' => ['required', Rule::in(StatementTrainer::ANSWERS)],
            'ms' => ['nullable', 'integer', 'min:0'],
        ]);

        $result = $this->trainer->answer(
            $statementSession,
            (int) $data['position'],
            $data['answer'],
            isset($data['ms']) ? (int) $data['ms'] : null
        );

        // Позиция не текущая: страница открыта в двух вкладках или устарела.
        if ($result === null) {
            return response()->json(['stale' => true], 409);
        }

        return response()->json($result);
    }

    public function report(Request $request, StatementSession $statementSession)
    {
        $this->own($request, $statementSession);
        $data = $request->validate([
            'statement_id' => ['required', 'integer'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        // Жаловаться можно только на карточку из своей сессии.
        abort_unless(in_array((int) $data['statement_id'], array_map('intval', array_column($statementSession->items, 'id')), true), 422);

        StatementReport::create([
            'user_id' => $request->user()->id,
            'statement_id' => Statement::findOrFail($data['statement_id'])->id,
            'message' => $data['message'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    private function own(Request $request, StatementSession $session): void
    {
        abort_unless((int) $session->user_id === (int) $request->user()->id, 404);
    }
}
