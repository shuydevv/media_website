<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\HistEvent;
use App\Models\HistFact;
use App\Models\HistReport;
use App\Models\HistSession;
use App\Models\UserHistEvent;
use App\Service\Dates\DateTrainer;
use App\Service\Dates\YearAnswer;
use App\Service\FishFoodService;
use App\Support\Text;
use DomainException;
use Illuminate\Http\Request;

/**
 * Тренажёр дат по истории: главная и пошаговая сессия. Устроен как
 * TermTrainerController; правила — в App\Service\Dates\DateTrainer.
 *
 * Правильные ответы в страницу не попадают: в способе А клиент знает лишь
 * число клеток и открытые подсказкой цифры, в квизе отправляет номер
 * варианта, а не id факта.
 */
class DateTrainerController extends Controller
{
    public function __construct(private DateTrainer $trainer, private FishFoodService $fish)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $progress = UserHistEvent::where('user_id', $user->id)->get()->keyBy('event_id');
        $items = HistEvent::active()->orderBy('sort_order')->get();

        $levels = [];
        $earlierUnfinished = false;
        foreach (HistEvent::LEVELS as $level => $label) {
            $ofLevel = $items->where('level', $level);
            if ($ofLevel->isEmpty()) {
                continue;
            }
            $learned = $ofLevel->filter(fn ($t) => $progress->has($t->id))->count();
            $levels[$level] = [
                'label' => $label,
                'total' => $ofLevel->count(),
                'learned' => $learned,
                'eras' => $ofLevel->groupBy('era')->sortKeys(),
                'locked' => $earlierUnfinished,
            ];
            $earlierUnfinished = $earlierUnfinished || $learned < $ofLevel->count();
        }

        return view('student.dates.index', [
            'overview' => $this->trainer->overview($user),
            'levels' => $levels,
            'progress' => $progress,
            'eras' => $this->eras(),
            'maxStep' => count(config('dates.intervals')),
            'cormBalance' => (int) $user->fish_corm_balance,
            'weakItems' => $items->filter(fn ($t) => $progress->get($t->id)?->last_failed)->values(),
        ]);
    }

    public function start(Request $request, string $kind)
    {
        try {
            $session = $this->trainer->start($request->user(), $kind);
        } catch (DomainException $e) {
            return redirect()->route('student.dates.index');
        }

        return redirect()->route('student.dates.session', $session);
    }

    public function show(Request $request, HistSession $histSession)
    {
        $this->own($request, $histSession);
        $histSession = $this->trainer->prepare($histSession);

        return view('student.dates.session', $this->screen($request, $histSession));
    }

    public function card(Request $request, HistSession $histSession)
    {
        $this->own($request, $histSession);

        return $this->respond($request, $this->trainer->card($histSession, $request->boolean('remembered')));
    }

    public function hint(Request $request, HistSession $histSession)
    {
        $this->own($request, $histSession);

        return $this->respond($request, $this->trainer->hint($histSession));
    }

    public function answer(Request $request, HistSession $histSession)
    {
        $this->own($request, $histSession);
        $typed = mb_substr((string) $request->input('typed', ''), 0, 40);

        return $this->respond($request, $this->trainer->answer($histSession, $typed, $request->boolean('reveal')));
    }

    public function choose(Request $request, HistSession $histSession)
    {
        $this->own($request, $histSession);

        return $this->respond($request, $this->trainer->choose($histSession, (int) $request->input('choice', -1)));
    }

    public function next(Request $request, HistSession $histSession)
    {
        $this->own($request, $histSession);

        return $this->respond($request, $this->trainer->next($histSession));
    }

    /** «Сообщить об ошибке» в квизе — как у терминов. */
    public function report(Request $request, HistSession $histSession)
    {
        $this->own($request, $histSession);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:500']]);

        $current = $histSession->state['current'] ?? null;
        if (($current['type'] ?? null) === 'b' && !empty($current['options'])) {
            $correct = $this->trainer->quizFacts($current)[$current['slot']] ?? null;

            if ($correct) {
                HistReport::create([
                    'user_id' => $request->user()->id,
                    'event_id' => $current['event_id'],
                    'fact_id' => $correct->id,
                    'distractor_ids' => array_values(array_diff($current['options'], [$correct->id])),
                    'comment' => $data['comment'] ?? null,
                ]);
            }
        }

        if ($request->header('HX-Request') === 'true') {
            return response('')->header('HX-Trigger', json_encode(['toast' => ['message' => 'Спасибо, передали']]));
        }

        return redirect()->route('student.dates.session', $histSession);
    }

    /** Карточка изученного события или правления — справочник с карты. */
    public function item(Request $request, HistEvent $histEvent)
    {
        $row = UserHistEvent::where('user_id', $request->user()->id)->where('event_id', $histEvent->id)->first();
        abort_if(!$histEvent->is_active || !$row, 404);

        return view('student.dates.show', [
            'item' => $histEvent->load('facts'),
            'row' => $row,
            'eras' => $this->eras(),
            'maxStep' => count(config('dates.intervals')),
        ]);
    }

    /** Названия периодов — из того же файла, что и события. */
    private function eras(): array
    {
        static $eras = null;
        if ($eras === null) {
            $data = json_decode((string) @file_get_contents(base_path('docs/history/events_base.json')), true);
            $eras = array_map('strval', $data['eras'] ?? []);
        }

        return $eras;
    }

    private function own(Request $request, HistSession $session): void
    {
        abort_if((int) $session->user_id !== (int) $request->user()->id, 404);
    }

    private function respond(Request $request, HistSession $session)
    {
        if ($request->header('HX-Request') !== 'true') {
            return redirect()->route('student.dates.session', $session);
        }

        return view('student.dates.partials.region', $this->screen($request, $session));
    }

    private function screen(Request $request, HistSession $session): array
    {
        $state = $session->state;
        $current = $state['current'] ?? null;
        $data = [
            'session' => $session,
            'kindLabel' => [
                HistSession::KIND_LEARN => 'Новые даты',
                HistSession::KIND_REVIEW => 'Повторение',
                HistSession::KIND_FREE => 'Свободная тренировка',
            ][$session->kind],
            'mascot' => $this->mascotData($request),
            'flash' => $state['flash'] ?? null,
        ];

        if ($session->finished_at || $current === null) {
            return $data + ['step' => 'result'] + $this->resultData($request, $session);
        }

        $item = HistEvent::with('facts')->find($current['event_id']);
        $data += [
            'item' => $item,
            'current' => $current,
            'combo' => (int) ($state['combo'] ?? 0),
            'cardKey' => (int) ($state['n'] ?? 0),
            'stackDepth' => max(0, min(2, count($state['queue']) - 1)),
            'say' => null,
            'autoNext' => null,
        ] + $this->progressData($session, $state);

        if ($current['type'] === 'card') {
            return $data + ['step' => 'card'];
        }

        if ($current['type'] === 'a') {
            $years = new YearAnswer();
            $start = (int) $item->year_start;
            $end = $item->year_end === null ? null : (int) $item->year_end;
            $cells = $years->cells($start, $end);
            $freeHints = (int) config('dates.free_hints');
            $result = $current['result'];

            return [
                'say' => $result === null ? null : match (true) {
                    $result['revealed'] => $this->say('year_revealed', false, ['year' => $item->yearsLabel()]),
                    !$result['correct'] => $this->sayAboutWrongYear($item, $result['input']),
                    $this->missedBefore($session, $state, $item->id, 'a') => $this->say('year_right_again', true, [], $state),
                    default => $this->say('year_right', true, [], $state),
                },
                'autoNext' => $result !== null && $result['correct'] ? 2200 : null,
                'typedCells' => $result !== null ? str_split($years->digits($result['input'])) : [],
            ] + $data + [
                'step' => 'a',
                'cellCount' => count($cells),
                'splitAt' => $years->splitAt($start, $end),
                'revealed' => $result !== null ? $cells : array_slice($cells, 0, $current['hints']),
                'hintsLeft' => max(0, $freeHints - $current['hints']),
                'freeHints' => $freeHints,
            ];
        }

        $optionIds = $current['type'] === 'b' && ($current['done'] ?? false)
            ? ($current['final_options'] ?? [])
            : $current['options'];
        $optionTexts = HistFact::whereIn('id', $optionIds)->pluck('text', 'id');
        $options = array_map(fn ($id) => $optionTexts[$id] ?? '', $optionIds);

        if ($current['type'] === 'c') {
            $answered = $current['done'] || $current['reveal'] !== null;
            $odd = $answered ? HistFact::with('event')->find($current['odd']) : null;

            return [
                'say' => !$answered ? null : ($current['done']
                    ? $this->say('odd_right', true, ['title' => $odd?->event?->title], $state)
                    : $this->say('odd_wrong', false, ['title' => $odd?->event?->title])),
                'autoNext' => $current['done'] ? 3200 : null,
            ] + $data + [
                'step' => 'c',
                'options' => $options,
                'oddIndex' => $answered ? array_search($current['odd'], $current['options'], true) : null,
            ];
        }

        $facts = $this->trainer->quizFacts($current);
        $correctIndex = $current['done'] ? null : array_search($facts[$current['slot']]->id, $current['options'], true);

        return [
            'say' => match (true) {
                $current['done'] => $this->say('quiz_right', true, ['count' => count($current['slots'])], $state),
                $current['reveal'] !== null => $this->sayAboutWrongFact(
                    $item,
                    HistFact::with('event')->find($current['options'][$current['reveal']['choice']])?->event
                ),
                default => null,
            },
            'autoNext' => $current['done'] ? 2200 : null,
        ] + $data + [
            'step' => 'b',
            'facts' => $facts,
            'chosenIndex' => ($current['done'] ?? false)
                ? array_search($facts[count($facts) - 1]->id, $current['final_options'] ?? [], true)
                : null,
            'options' => $options,
            'correctIndex' => $current['reveal'] !== null ? $correctIndex : null,
        ];
    }

    /** Реплика рыбы — как TermTrainerController::say(), фразы из config('dates.phrases'). */
    private function say(string $situation, bool $ok, array $vars = [], ?array $state = null): array
    {
        $pool = config("dates.phrases.{$situation}", []);

        $combo = (int) ($state['combo'] ?? 0);
        if ($ok && $state !== null && config("dates.phrases.combo.{$combo}")) {
            $pool = config("dates.phrases.combo.{$combo}");
        }

        $last = session('dates.last_phrase');
        $fresh = array_values(array_filter($pool, fn (string $phrase) => $phrase !== $last));
        $phrase = $fresh ? $fresh[array_rand($fresh)] : ($pool[0] ?? '');
        session(['dates.last_phrase' => $phrase]);

        return ['ok' => $ok, 'text' => strtr($phrase, [
            ':title' => (string) ($vars['title'] ?? ''),
            ':other' => (string) ($vars['other'] ?? ''),
            ':typed' => (string) ($vars['typed'] ?? ''),
            ':year' => (string) ($vars['year'] ?? ''),
            ':diff' => (string) ($vars['diff'] ?? ''),
            ':count' => (string) ($vars['count'] ?? ''),
        ])];
    }

    /**
     * Неверный год. По порядку:
     *  1) введены годы другого события или правления из базы — называем
     *     его (и заметку автора к паре, если она есть);
     *  2) промах не больше config('dates.almost_years') — «почти»;
     *  3) иначе называем правильный год.
     * Правильный год ученик и так видит в клетках.
     */
    private function sayAboutWrongYear(HistEvent $item, string $input): array
    {
        $years = new YearAnswer();
        $digits = $years->digits($input);
        $start = (int) $item->year_start;
        $end = $item->year_end === null ? null : (int) $item->year_end;

        $confusable = $item->confusables()->pluck('hist_events.id')->all();
        $other = HistEvent::active()->where('id', '!=', $item->id)->get()
            ->filter(fn (HistEvent $o) => $years->expected((int) $o->year_start, $o->year_end === null ? null : (int) $o->year_end) === $digits)
            ->sortBy(fn (HistEvent $o) => in_array($o->id, $confusable) ? 0 : 1)
            ->first();

        if ($other) {
            $say = $this->say('year_other', false, ['other' => $other->title, 'typed' => $other->yearsLabel()]);
            $note = $this->pairNote($item, $other);

            return $note !== '' ? ['ok' => false, 'text' => rtrim($say['text'], '. ').'. '.$note] : $say;
        }

        $miss = $years->missBy($digits, $start, $end);
        if ($miss !== null && $miss > 0 && $miss <= (int) config('dates.almost_years')) {
            return $this->say('year_almost', false, ['diff' => Text::plural($miss, ['год', 'года', 'лет'])]);
        }

        return $this->say('year_wrong', false, ['year' => $item->yearsLabel()]);
    }

    /** Квиз, неверный клик: про что этот факт и, если есть, почему их легко спутать. */
    private function sayAboutWrongFact(HistEvent $item, ?HistEvent $owner): array
    {
        $say = $this->say('quiz_wrong', false, ['title' => $owner?->title]);
        if (!$owner) {
            return $say;
        }

        $tail = $this->pairNote($item, $owner);
        $isConfusable = $item->confusables()->where('hist_events.id', $owner->id)->exists()
            || $owner->confusables()->where('hist_events.id', $item->id)->exists();
        if ($tail === '' && $isConfusable) {
            $tail = $this->say('quiz_wrong_confusable', false)['text'];
        }

        return ['ok' => false, 'text' => rtrim(trim($say['text'].'. '.$tail), '. ')];
    }

    /** Заметка автора к паре — записана у любого из двух. */
    private function pairNote(HistEvent $a, HistEvent $b): string
    {
        return trim((string) (($a->pair_notes[$b->slug] ?? null) ?? ($b->pair_notes[$a->slug] ?? '')));
    }

    private function missedBefore(HistSession $session, array $state, int $id, string $method): bool
    {
        $first = $session->kind === HistSession::KIND_LEARN
            ? ($state['first'][$method][$id] ?? null)
            : ($state['first'][$id] ?? null);

        return $first === false;
    }

    /**
     * Картинки маскота для сессии. Свой маскот тренажёра —
     * config('dates.mascot') (черепаха Душнила); без него — акула ученика.
     */
    private function mascotData(Request $request): array
    {
        if ($own = config('dates.mascot')) {
            return array_map(fn (string $path) => asset($path), $own);
        }

        $level = $this->fish->levelFor((int) $request->user()->fish_total_fed);

        return [
            'default' => $this->fish->mascotImageUrl($level),
            'correct' => $this->fish->mascotImageUrl($level, 'correct'),
            'thinking' => $this->fish->mascotImageUrl($level, 'partly_correct'),
            'eating' => $this->fish->mascotImageUrl($level, 'eating'),
        ];
    }

    private function progressData(HistSession $session, array $state): array
    {
        $view = clone $session;
        $view->state = $state;
        $done = $this->trainer->doneCount($view);
        $total = max(1, $this->trainer->totalCount($view));
        $isLearn = $session->kind === HistSession::KIND_LEARN;

        return [
            'progressLabel' => ($isLearn ? 'Выучено' : 'Пройдено')." {$done} из {$total}",
            'percent' => (int) round($done / $total * 100),
        ];
    }

    private function resultData(Request $request, HistSession $session): array
    {
        $user = $request->user();
        $state = $session->state;
        $first = $state['first'] ?? [];
        $isLearn = $session->kind === HistSession::KIND_LEARN;

        if ($isLearn) {
            $ids = $state['terms'] ?? [];
            $okOf = fn (int $id) => ($first['a'][$id] ?? true) !== false && ($first['b'][$id] ?? true) !== false;
        } else {
            $ids = array_map('intval', array_keys($first));
            $okOf = fn (int $id) => ($first[$id] ?? true) !== false;
        }

        $items = HistEvent::whereIn('id', $ids)->get()->keyBy('id');
        $dots = [];
        foreach ($ids as $id) {
            if ($items->has($id)) {
                $dots[] = ['title' => $items[$id]->title, 'ok' => $okOf((int) $id)];
            }
        }
        $failedIds = array_values(array_filter($ids, fn ($id) => !$okOf((int) $id)));

        $say = match (true) {
            $session->kind === HistSession::KIND_FREE => 'result_free',
            $failedIds === [] => 'result_perfect',
            (int) $session->corm_awarded > 0 => 'result_good',
            default => 'result_hard',
        };

        $overview = $this->trainer->overview($user);
        $freeOpen = in_array($overview['free']['status'], ['available', 'in_progress'], true);

        return [
            'resultSay' => $this->say($say, $failedIds === []),
            'dots' => $dots,
            'okCount' => count(array_filter($dots, fn ($d) => $d['ok'])),
            'failedItems' => HistEvent::whereIn('id', $failedIds)->orderBy('sort_order')->get(),
            'dueTomorrow' => $this->trainer->dueTomorrow($user),
            'nextReview' => $this->trainer->nextReview($user, now()->addDay()->toDateString()),
            'cormBalance' => (int) $user->fresh()->fish_corm_balance,
            'secondary' => $freeOpen
                ? ($session->kind === HistSession::KIND_FREE ? 'Ещё круг' : 'Свободная тренировка')
                : null,
        ];
    }
}
