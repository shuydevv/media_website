<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\PersonReport;
use App\Models\PersonSession;
use App\Models\UserPerson;
use App\Service\FishFoodService;
use App\Service\Persons\PersonRanks;
use App\Service\Persons\PersonTrainer;
use DomainException;
use Illuminate\Http\Request;

/**
 * Тренажёр личностей по истории: главная и пошаговая сессия. Устроен как
 * DateTrainerController; правила — в App\Service\Persons\PersonTrainer.
 *
 * Правильные ответы в страницу не попадают: в выборе клиент отправляет
 * номер варианта, во вводе фамилии знает лишь число клеток и открытые
 * буквы, в соответствии отправляет цифры по буквам.
 */
class PersonTrainerController extends Controller
{
    public const LETTERS = ['А', 'Б', 'В', 'Г', 'Д', 'Е'];

    public function __construct(private PersonTrainer $trainer, private FishFoodService $fish, private PersonRanks $ranks)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $progress = UserPerson::where('user_id', $user->id)->get()->keyBy('person_id');
        $items = Person::active()->orderBy('sort_order')->get();

        $levels = [];
        $earlierUnfinished = false;
        foreach (Person::LEVELS as $level => $label) {
            $ofLevel = $items->where('level', $level);
            if ($ofLevel->isEmpty()) {
                continue;
            }
            $learned = $ofLevel->filter(fn ($p) => $progress->has($p->id))->count();
            $levels[$level] = [
                'label' => $label,
                'total' => $ofLevel->count(),
                'learned' => $learned,
                'eras' => $ofLevel->sortBy('name')->groupBy('era')->sortKeys(),
                'locked' => $earlierUnfinished,
            ];
            $earlierUnfinished = $earlierUnfinished || $learned < $ofLevel->count();
        }

        return view('student.persons.index', [
            'overview' => $this->trainer->overview($user),
            'levels' => $levels,
            'progress' => $progress,
            'eras' => $this->eras(),
            'maxStep' => count(config('persons.intervals')),
            'cormBalance' => (int) $user->fish_corm_balance,
            'weakItems' => $items->filter(fn ($p) => $progress->get($p->id)?->last_failed)->values(),
            // Первый вход — знакомство с Адмиралом Клешней (один раз).
            'rankModal' => $this->rankModal($this->previewRank() ?? $this->ranks->introduce($user)),
            'rankSummary' => $this->ranks->summary($user),
        ]);
    }

    public function start(Request $request, string $kind)
    {
        try {
            $session = $this->trainer->start($request->user(), $kind);
        } catch (DomainException $e) {
            return redirect()->route('student.persons.index');
        }

        return redirect()->route('student.persons.session', $session);
    }

    public function show(Request $request, PersonSession $personSession)
    {
        $this->own($request, $personSession);
        $personSession = $this->trainer->prepare($personSession);

        return view('student.persons.session', $this->screen($request, $personSession));
    }

    public function card(Request $request, PersonSession $personSession)
    {
        $this->own($request, $personSession);

        return $this->respond($request, $this->trainer->card($personSession, $request->boolean('remembered')));
    }

    public function hint(Request $request, PersonSession $personSession)
    {
        $this->own($request, $personSession);

        return $this->respond($request, $this->trainer->hint($personSession));
    }

    public function answer(Request $request, PersonSession $personSession)
    {
        $this->own($request, $personSession);
        $typed = mb_substr((string) $request->input('typed', ''), 0, 60);

        return $this->respond($request, $this->trainer->answer($personSession, $typed, $request->boolean('reveal')));
    }

    public function choose(Request $request, PersonSession $personSession)
    {
        $this->own($request, $personSession);

        return $this->respond($request, $this->trainer->choose($personSession, (int) $request->input('choice', -1)));
    }

    public function match(Request $request, PersonSession $personSession)
    {
        $this->own($request, $personSession);
        $answer = mb_substr((string) $request->input('answer', ''), 0, 10);

        return $this->respond($request, $this->trainer->match($personSession, $answer));
    }

    public function next(Request $request, PersonSession $personSession)
    {
        $this->own($request, $personSession);

        return $this->respond($request, $this->trainer->next($personSession));
    }

    /** «Сообщить об ошибке» — в заданиях с вариантами. */
    public function report(Request $request, PersonSession $personSession)
    {
        $this->own($request, $personSession);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:500']]);

        $current = $personSession->state['current'] ?? null;
        if (in_array($current['type'] ?? null, ['b', 'c', 'd'], true)) {
            PersonReport::create([
                'user_id' => $request->user()->id,
                'person_id' => $current['type'] === 'd' ? $current['rows'][0] : $current['person_id'],
                'step' => $current['type'],
                'option_ids' => $current['type'] === 'd'
                    ? ['rows' => $current['rows'], 'options' => $current['options']]
                    : $current['options'],
                'comment' => $data['comment'] ?? null,
            ]);
        }

        if ($request->header('HX-Request') === 'true') {
            return response('')->header('HX-Trigger', json_encode(['toast' => ['message' => 'Спасибо, передали']]));
        }

        return redirect()->route('student.persons.session', $personSession);
    }

    /**
     * «Все личности» — справочник карточками: портрет, кто это, чем известен,
     * связи. Открыт целиком, не только изученное: это пособие, а в заданиях
     * ученик всё равно видит портреты с именами. Изученные отмечены.
     */
    public function all(Request $request)
    {
        $progress = UserPerson::where('user_id', $request->user()->id)->pluck('step', 'person_id');
        $people = Person::active()->orderBy('level')->orderBy('era')->orderBy('name')->get();

        return view('student.persons.all', [
            'levels' => $people->groupBy('level'),
            'progress' => $progress,
            'eras' => $this->eras(),
            'usedEras' => $people->pluck('era')->unique()->sort()->values(),
            'total' => $people->count(),
        ]);
    }

    /** Карточка изученной личности — справочник с карты. */
    public function item(Request $request, Person $person)
    {
        $row = UserPerson::where('user_id', $request->user()->id)->where('person_id', $person->id)->first();
        abort_if(!$person->is_active || !$row, 404);

        return view('student.persons.show', [
            'person' => $person,
            'row' => $row,
            'eras' => $this->eras(),
            'maxStep' => count(config('persons.intervals')),
        ]);
    }

    /** Названия периодов — из файла базы личностей. */
    private function eras(): array
    {
        static $eras = null;
        if ($eras === null) {
            $data = json_decode((string) @file_get_contents(base_path('docs/personalities/personalities.json')), true);
            $eras = array_map('strval', $data['eras'] ?? []);
        }

        return $eras;
    }

    private function own(Request $request, PersonSession $session): void
    {
        abort_if((int) $session->user_id !== (int) $request->user()->id, 404);
    }

    private function respond(Request $request, PersonSession $session)
    {
        if ($request->header('HX-Request') !== 'true') {
            return redirect()->route('student.persons.session', $session);
        }

        return view('student.persons.partials.region', $this->screen($request, $session));
    }

    private function screen(Request $request, PersonSession $session): array
    {
        $state = $session->state;
        $current = $state['current'] ?? null;
        $data = [
            'session' => $session,
            'kindLabel' => [
                PersonSession::KIND_LEARN => 'Новые личности',
                PersonSession::KIND_REVIEW => 'Повторение',
                PersonSession::KIND_FREE => 'Свободная тренировка',
            ][$session->kind],
            'mascot' => $this->mascotData($request),
            'flash' => $state['flash'] ?? null,
            'eras' => $this->eras(),
        ];

        if ($session->finished_at || $current === null) {
            return $data + ['step' => 'result'] + $this->resultData($request, $session);
        }

        $data += [
            'current' => $current,
            'combo' => (int) ($state['combo'] ?? 0),
            'cardKey' => (int) ($state['n'] ?? 0),
            'stackDepth' => max(0, min(2, count($state['queue']) - 1)),
            'say' => null,
            'autoNext' => null,
        ] + $this->progressData($session, $state);

        if ($current['type'] === 'd') {
            return $this->matchScreen($data, $current, $state);
        }

        $person = Person::find($current['person_id']);
        $data['person'] = $person;

        if ($current['type'] === 'card') {
            return $data + ['step' => 'card', 'related' => $this->relatedOf($person)];
        }

        if ($current['type'] === 'a') {
            $matcher = $this->trainer->matcher();
            $cells = $matcher->cells($person->answer);
            $freeHints = (int) config('persons.free_hints');
            $result = $current['result'];

            return [
                'say' => $result === null ? null : match (true) {
                    $result['revealed'] => $this->say('type_revealed', false, ['name' => $person->name]),
                    !$result['correct'] && $matcher->isClose($result['input'], [$person->answer]) => $this->say('type_close', false, ['name' => $person->name]),
                    !$result['correct'] => $this->say('type_wrong', false, ['name' => $person->name]),
                    $this->missedBefore($session, $state, $person->id, 'a') => $this->say('type_right_again', true, [], $state),
                    default => $this->say('type_right', true, [], $state),
                },
                'autoNext' => $result !== null && $result['correct'] ? 2200 : null,
                'typedCells' => $result !== null ? $matcher->cells($result['input']) : [],
            ] + $data + [
                'step' => 'a',
                'cellCount' => count($cells),
                'revealed' => $result !== null ? $cells : array_slice($cells, 0, $current['hints']),
                'hintsLeft' => max(0, $freeHints - $current['hints']),
                'freeHints' => $freeHints,
            ];
        }

        // b, c — выбор из четырёх.
        $optionPeople = Person::whereIn('id', $current['options'])->get()->keyBy('id');
        $options = array_map(fn ($id) => $optionPeople[$id] ?? null, $current['options']);
        $correctIndex = array_search((int) $person->id, array_map('intval', $current['options']), true);
        $answered = $current['done'] || $current['reveal'] !== null;
        $chosen = $current['reveal'] !== null ? $options[$current['reveal']['choice']] ?? null : null;

        $say = null;
        if ($current['done']) {
            $say = $this->say($current['type'] === 'b' ? 'known_right' : 'who_right', true, [], $state);
        } elseif ($chosen) {
            $say = $current['type'] === 'b'
                ? $this->say('known_wrong', false, ['name' => $chosen->name])
                : $this->say('who_wrong', false, ['name' => $chosen->name, 'what' => $this->lowerFirst(rtrim($chosen->description, '. '))]);
        }

        return [
            'say' => $say,
            'autoNext' => $current['done'] ? 2200 : null,
        ] + $data + [
            'step' => $current['type'],
            'options' => $options,
            'answered' => $answered,
            'correctIndex' => $answered ? $correctIndex : null,
            'chosenIndex' => $current['reveal']['choice'] ?? null,
            'hintText' => $current['type'] === 'c' && ($current['hint'] ?? false) ? $person->related_text : null,
            'hasHint' => $current['type'] === 'c' && $person->related_text !== null,
        ];
    }

    /** Соответствие: строки — ассоциации (события), справа — личности по номерам. */
    private function matchScreen(array $data, array $current, array $state): array
    {
        $people = Person::whereIn('id', array_merge($current['rows'], $current['options']))->get()->keyBy('id');
        $result = $current['result'];

        $rows = [];
        foreach ($current['rows'] as $i => $id) {
            $rightDigit = array_search((int) $id, array_map('intval', $current['options']), true) + 1;
            $rows[] = [
                'letter' => self::LETTERS[$i],
                'text' => $people[$id]->association,
                'ok' => $result['ok'][$i] ?? null,
                'chosen' => $result ? (int) ($result['answer'][$i] ?? 0) : null,
                // Правильную цифру отдаём только после ответа.
                'right' => $result ? $rightDigit : null,
                'rightName' => $result ? $people[$id]->name : null,
            ];
        }
        $options = array_map(fn ($id) => $people[$id], $current['options']);

        $say = null;
        $okCount = $result ? count(array_filter($result['ok'])) : 0;
        if ($result) {
            $say = match (true) {
                $okCount === count($rows) => $this->say('match_perfect', true, ['count' => $okCount], $state),
                $okCount > 0 => $this->say('match_partial', false, ['count' => $okCount]),
                default => $this->say('match_wrong', false),
            };
        }

        return [
            'say' => $say,
            'autoNext' => $result && $okCount === count($rows) ? 2600 : null,
        ] + $data + [
            'step' => 'd',
            'rows' => $rows,
            'options' => $options,
            'result' => $result,
        ];
    }

    /** Связанные личности для карточки: текст и имена со ссылками на изученных. */
    private function relatedOf(Person $person): ?string
    {
        return $person->related_text;
    }

    private function lowerFirst(string $text): string
    {
        return mb_strtolower(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    /** Реплика маскота — как DateTrainerController::say(), фразы из config('persons.phrases'). */
    private function say(string $situation, bool $ok, array $vars = [], ?array $state = null): array
    {
        $pool = config("persons.phrases.{$situation}", []);

        $combo = (int) ($state['combo'] ?? 0);
        if ($ok && $state !== null && config("persons.phrases.combo.{$combo}")) {
            $pool = config("persons.phrases.combo.{$combo}");
        }

        $last = session('persons.last_phrase');
        $fresh = array_values(array_filter($pool, fn (string $phrase) => $phrase !== $last));
        $phrase = $fresh ? $fresh[array_rand($fresh)] : ($pool[0] ?? '');
        session(['persons.last_phrase' => $phrase]);

        return ['ok' => $ok, 'text' => strtr($phrase, [
            ':name' => (string) ($vars['name'] ?? ''),
            ':what' => (string) ($vars['what'] ?? ''),
            ':count' => (string) ($vars['count'] ?? ''),
            ':rank' => $this->ranks->address(auth()->user()),
        ])];
    }

    private function missedBefore(PersonSession $session, array $state, int $id, string $method): bool
    {
        $first = $session->kind === PersonSession::KIND_LEARN
            ? ($state['first'][$method][$id] ?? null)
            : ($state['first'][$id] ?? null);

        return $first === false;
    }

    private function mascotData(Request $request): array
    {
        if ($own = config('persons.mascot')) {
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

    /**
     * Только локально: ?rank_preview=N показывает окно звания N, ничего не
     * записывая (для проверки вёрстки окон «Приказ по флоту»).
     */
    private function previewRank(): ?array
    {
        $n = (int) request()->query('rank_preview');
        if (!app()->isLocal() || $n < 1 || !($rank = $this->ranks->rank($n))) {
            return null;
        }

        return $rank + ['skipped' => false, 'inLine' => (int) $rank['need']];
    }

    /** Звание → данные окна «Приказ по флоту» (partials/rank-modal). */
    private function rankModal(?array $rank): ?array
    {
        if ($rank === null) {
            return null;
        }
        $next = $this->ranks->rank($rank['n'] + 1);

        return $rank + [
            'image' => asset(config('persons.mascot_'.$rank['mascot'], config('persons.mascot_salute'))),
            'intro' => $rank['n'] === 1,
            'nextTitle' => $next['title'] ?? null,
            'left' => $next && $next['need'] ? max(0, $next['need'] - ($rank['inLine'] ?? 0)) : null,
        ];
    }

    private function progressData(PersonSession $session, array $state): array
    {
        $view = clone $session;
        $view->state = $state;
        $done = $this->trainer->doneCount($view);
        $total = max(1, $this->trainer->totalCount($view));
        $isLearn = $session->kind === PersonSession::KIND_LEARN;

        return [
            'progressLabel' => ($isLearn ? 'Выучено' : 'Пройдено')." {$done} из {$total}",
            'percent' => (int) round($done / $total * 100),
        ];
    }

    private function resultData(Request $request, PersonSession $session): array
    {
        $user = $request->user();
        $state = $session->state;
        $first = $state['first'] ?? [];
        $isLearn = $session->kind === PersonSession::KIND_LEARN;

        if ($isLearn) {
            $ids = $state['terms'] ?? [];
            $okOf = fn (int $id) => ($first['b'][$id] ?? true) !== false && ($first['c'][$id] ?? true) !== false;
        } else {
            $ids = array_map('intval', array_keys($first));
            $okOf = fn (int $id) => ($first[$id] ?? true) !== false;
        }

        $people = Person::whereIn('id', $ids)->get()->keyBy('id');
        $dots = [];
        foreach ($ids as $id) {
            if ($people->has($id)) {
                $dots[] = ['title' => $people[$id]->name, 'ok' => $okOf((int) $id)];
            }
        }
        $failedIds = array_values(array_filter($ids, fn ($id) => !$okOf((int) $id)));

        $say = match (true) {
            $session->kind === PersonSession::KIND_FREE => 'result_free',
            $failedIds === [] => 'result_perfect',
            (int) $session->corm_awarded > 0 => 'result_good',
            default => 'result_hard',
        };

        $match = $state['match'] ?? [];
        $overview = $this->trainer->overview($user);
        $freeOpen = in_array($overview['free']['status'], ['available', 'in_progress'], true);

        return [
            'resultSay' => $this->say($say, $failedIds === []),
            // Новое звание — окно «Приказ по флоту» после корма (один раз).
            'rankModal' => $this->rankModal($this->ranks->promote($user)),
            'dots' => $dots,
            'okCount' => count(array_filter($dots, fn ($d) => $d['ok'])),
            'failedItems' => Person::whereIn('id', $failedIds)->orderBy('sort_order')->get(),
            'matchOk' => array_sum(array_column($match, 'ok')),
            'matchOf' => array_sum(array_column($match, 'of')),
            'dueTomorrow' => $this->trainer->dueTomorrow($user),
            'nextReview' => $this->trainer->nextReview($user, now()->addDay()->toDateString()),
            'cormBalance' => (int) $user->fresh()->fish_corm_balance,
            'secondary' => $freeOpen
                ? ($session->kind === PersonSession::KIND_FREE ? 'Ещё круг' : 'Свободная тренировка')
                : null,
        ];
    }
}
