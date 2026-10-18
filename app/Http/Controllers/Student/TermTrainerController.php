<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Term;
use App\Models\TermFeature;
use App\Models\TermReport;
use App\Models\TermSession;
use App\Models\UserTerm;
use App\Service\FishFoodService;
use App\Service\Terms\AnswerMatcher;
use App\Service\Terms\TermRelations;
use App\Service\Terms\TermTrainer;
use DomainException;
use Illuminate\Http\Request;

/**
 * Тренажёр терминов (задание 18): главная с блоком «Сегодня» и картой
 * терминов, и пошаговая сессия. Правила — в App\Service\Terms\TermTrainer,
 * здесь только сборка данных для экрана.
 *
 * Правильные ответы в страницу не попадают: в способе А клиент знает лишь
 * число квадратиков и открытые подсказкой буквы, в способе Б отправляет
 * номер варианта, а не id признака (по id верный вариант вычислялся бы —
 * у признаков одного термина они идут подряд).
 */
class TermTrainerController extends Controller
{
    public const SECTIONS = [
        1 => 'Человек и общество',
        2 => 'Экономика',
        3 => 'Социальные отношения',
        4 => 'Политика',
        5 => 'Право',
    ];

    public function __construct(private TermTrainer $trainer, private FishFoodService $fish)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $progress = UserTerm::where('user_id', $user->id)->get()->keyBy('term_id');
        $terms = Term::active()->orderBy('sort_order')->get();

        $levels = [];
        // Уровень закрыт, пока в каком-то из предыдущих есть неизученные
        // термины — тот же порядок, что и у порций (TermTrainer::nextBatch()).
        $earlierUnfinished = false;
        foreach (Term::LEVELS as $priority => $label) {
            $ofLevel = $terms->where('priority', $priority);
            $learned = $ofLevel->filter(fn ($t) => $progress->has($t->id))->count();
            $levels[$priority] = [
                'label' => $label,
                'total' => $ofLevel->count(),
                'learned' => $learned,
                'sections' => $ofLevel->groupBy('section')->sortKeys(),
                'locked' => $earlierUnfinished,
            ];
            $earlierUnfinished = $earlierUnfinished || $learned < $ofLevel->count();
        }

        return view('student.terms.index', [
            'overview' => $this->trainer->overview($user),
            'levels' => $levels,
            'progress' => $progress,
            'sections' => self::SECTIONS,
            'maxStep' => count(config('terms.intervals')),
            'cormBalance' => (int) $user->fish_corm_balance,
            // Термины, последняя проверка которых закончилась ошибкой.
            'weakTerms' => $terms->filter(fn ($t) => $progress->get($t->id)?->last_failed)->values(),
        ]);
    }

    public function start(Request $request, string $kind)
    {
        try {
            $session = $this->trainer->start($request->user(), $kind);
        } catch (DomainException $e) {
            return redirect()->route('student.terms.index');
        }

        return redirect()->route('student.terms.session', $session);
    }

    public function show(Request $request, TermSession $termSession)
    {
        $this->own($request, $termSession);
        $termSession = $this->trainer->prepare($termSession);

        return view('student.terms.session', $this->screen($request, $termSession));
    }

    public function card(Request $request, TermSession $termSession)
    {
        $this->own($request, $termSession);

        return $this->respond($request, $this->trainer->card($termSession, $request->boolean('remembered')));
    }

    public function hint(Request $request, TermSession $termSession)
    {
        $this->own($request, $termSession);

        return $this->respond($request, $this->trainer->hint($termSession));
    }

    public function answer(Request $request, TermSession $termSession)
    {
        $this->own($request, $termSession);
        $typed = mb_substr((string) $request->input('typed', ''), 0, 120);

        return $this->respond($request, $this->trainer->answer($termSession, $typed, $request->boolean('reveal')));
    }

    public function choose(Request $request, TermSession $termSession)
    {
        $this->own($request, $termSession);

        return $this->respond($request, $this->trainer->choose($termSession, (int) $request->input('choice', -1)));
    }

    public function next(Request $request, TermSession $termSession)
    {
        $this->own($request, $termSession);

        return $this->respond($request, $this->trainer->next($termSession));
    }

    /**
     * «Сообщить об ошибке» в квизе: сохраняет верный признак текущего слота и
     * дистракторы, которые ученик видел.
     */
    public function report(Request $request, TermSession $termSession)
    {
        $this->own($request, $termSession);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:500']]);

        $current = $termSession->state['current'] ?? null;
        if (($current['type'] ?? null) === 'b' && !empty($current['options'])) {
            $correct = $this->trainer->quizFeatures($current)[$current['slot']] ?? null;

            if ($correct) {
                TermReport::create([
                    'user_id' => $request->user()->id,
                    'term_id' => $current['term_id'],
                    'feature_id' => $correct->id,
                    'distractor_ids' => array_values(array_diff($current['options'], [$correct->id])),
                    'comment' => $data['comment'] ?? null,
                ]);
            }
        }

        if ($request->header('HX-Request') === 'true') {
            return response('')->header('HX-Trigger', json_encode(['toast' => ['message' => 'Спасибо, передали']]));
        }

        return redirect()->route('student.terms.session', $termSession);
    }

    /** Карточка изученного термина — справочник с карты терминов. */
    public function term(Request $request, Term $term)
    {
        $row = UserTerm::where('user_id', $request->user()->id)->where('term_id', $term->id)->first();
        abort_if(!$term->is_active || !$row, 404);

        return view('student.terms.show', [
            'term' => $term->load('features'),
            'row' => $row,
            'maxStep' => count(config('terms.intervals')),
        ]);
    }

    private function own(Request $request, TermSession $session): void
    {
        abort_if((int) $session->user_id !== (int) $request->user()->id, 404);
    }

    private function respond(Request $request, TermSession $session)
    {
        if ($request->header('HX-Request') !== 'true') {
            return redirect()->route('student.terms.session', $session);
        }

        return view('student.terms.partials.region', $this->screen($request, $session));
    }

    /**
     * Данные текущего экрана сессии.
     */
    private function screen(Request $request, TermSession $session): array
    {
        $state = $session->state;
        $current = $state['current'] ?? null;
        $data = [
            'session' => $session,
            'kindLabel' => [
                TermSession::KIND_LEARN => 'Новые термины',
                TermSession::KIND_REVIEW => 'Повторение',
                TermSession::KIND_FREE => 'Свободная тренировка',
            ][$session->kind],
            'mascot' => $this->mascotData($request),
            // Что произошло в последнем действии — экран выбирает по этому
            // анимацию (см. scripts в session.blade.php).
            'flash' => $state['flash'] ?? null,
        ];

        if ($session->finished_at || $current === null) {
            return $data + ['step' => 'result'] + $this->resultData($request, $session);
        }

        $term = Term::with('features')->find($current['term_id']);
        $data += [
            'term' => $term,
            'current' => $current,
            'combo' => (int) ($state['combo'] ?? 0),
            // Номер карточки: изменился — старую карточку «отбрасываем».
            'cardKey' => (int) ($state['n'] ?? 0),
            // Сколько карточек видно в стопке под текущей.
            'stackDepth' => max(0, min(2, count($state['queue']) - 1)),
            // Реплика рыбы после ответа (см. say()); null — молчит.
            'say' => null,
            // Верный ответ не требует «Далее»: через столько миллисекунд
            // экран сам перейдёт к следующему заданию. null — ждём кнопку.
            'autoNext' => null,
        ] + $this->progressData($session, $state);

        if ($current['type'] === 'card') {
            return $data + ['step' => 'card'];
        }

        if ($current['type'] === 'a') {
            $cells = AnswerMatcher::fromConfig()->cells($term->title);
            $freeHints = (int) config('terms.free_hints');

            $result = $current['result'];

            return [
                'say' => $result === null ? null : match (true) {
                    $result['revealed'] => $this->say('name_revealed', false, ['term' => $this->nameOf($term)]),
                    !$result['correct'] => $this->sayAboutWrongName($term, $result['input']),
                    // Термин уже возвращался после ошибки и теперь назван верно.
                    $this->missedBefore($session, $state, $term->id, 'a') => $this->say('name_right_again', true, [], $state),
                    default => $this->say('name_right', true, [], $state),
                },
                'autoNext' => $result !== null && $result['correct'] ? 2200 : null,
                // Что ввёл ученик, по клеткам: при ошибке его буквы на глазах
                // переворачиваются в правильные.
                'typedCells' => $result !== null ? AnswerMatcher::fromConfig()->cells($result['input']) : [],
            ] + $data + [
                'step' => 'a',
                'cellCount' => count($cells),
                // После ответа показываем название целиком, до него — только
                // буквы, открытые подсказкой.
                'revealed' => $current['result'] !== null ? $cells : array_slice($cells, 0, $current['hints']),
                'hintsLeft' => max(0, $freeHints - $current['hints']),
                'freeHints' => $freeHints,
            ];
        }

        // Пройденный квиз показывает варианты последнего вопроса погасшими —
        // они сохранены отдельно (final_options). У «лишнего признака»
        // варианты после ответа остаются в options как были.
        $optionIds = $current['type'] === 'b' && ($current['done'] ?? false)
            ? ($current['final_options'] ?? [])
            : $current['options'];
        $optionTexts = TermFeature::whereIn('id', $optionIds)->pluck('text', 'id');
        $options = array_map(fn ($id) => $optionTexts[$id] ?? '', $optionIds);

        if ($current['type'] === 'c') {
            // Какой вариант лишний и от какого он понятия, страница узнаёт
            // только после ответа.
            $answered = $current['done'] || $current['reveal'] !== null;
            $odd = $answered ? TermFeature::with('term')->find($current['odd']) : null;

            return [
                'say' => !$answered ? null : ($current['done']
                    ? $this->say('odd_right', true, ['term' => $this->nameOf($odd?->term)], $state)
                    : $this->say('odd_wrong', false, ['term' => $this->nameOf($odd?->term)])),
                // Дольше, чем в других заданиях: в реплике есть что прочитать.
                'autoNext' => $current['done'] ? 3200 : null,
            ] + $data + [
                'step' => 'c',
                'options' => $options,
                'oddIndex' => $answered ? array_search($current['odd'], $current['options'], true) : null,
                'oddTerm' => $odd?->term,
            ];
        }

        // Не все признаки термина, а выбранные для этого квиза (не больше четырёх).
        $features = $this->trainer->quizFeatures($current);
        $correctIndex = $current['done'] ? null : array_search($features[$current['slot']]->id, $current['options'], true);

        return [
            'say' => match (true) {
                $current['done'] => $this->say('quiz_right', true, ['count' => count($current['slots'])], $state),
                // На ошибку — не «верный вариант зелёный» (это и так видно), а
                // факт: от какого понятия признак, который ученик выбрал.
                $current['reveal'] !== null => $this->sayAboutWrongFeature(
                    $term,
                    TermFeature::with('term')->find($current['options'][$current['reveal']['choice']])?->term
                ),
                default => null,
            },
            'autoNext' => $current['done'] ? 2200 : null,
        ] + $data + [
            'step' => 'b',
            'features' => $features,
            // Какой из погасших вариантов был выбран последним (верный
            // признак последнего слота) — на его месте пунктирная рамка.
            'chosenIndex' => ($current['done'] ?? false)
                ? array_search($features[count($features) - 1]->id, $current['final_options'] ?? [], true)
                : null,
            'options' => $options,
            // Верный вариант уходит в страницу только после неверного клика.
            'correctIndex' => $current['reveal'] !== null ? $correctIndex : null,
        ];
    }

    /**
     * Реплика рыбы: случайная фраза ситуации из config('terms.phrases'), но
     * не та же, что прозвучала в прошлый раз. На верном ответе круглая серия
     * (5, 10 подряд) заменяет обычную похвалу.
     *
     * @param array{term?: ?string, count?: int} $vars подстановки :term / :count
     * @param array|null $state состояние сессии — нужно только для серии
     * @return array{ok: bool, text: string}
     */
    private function say(string $situation, bool $ok, array $vars = [], ?array $state = null): array
    {
        $pool = config("terms.phrases.{$situation}", []);

        $combo = (int) ($state['combo'] ?? 0);
        if ($ok && $state !== null && config("terms.phrases.combo.{$combo}")) {
            $pool = config("terms.phrases.combo.{$combo}");
        }

        $last = session('terms.last_phrase');
        $fresh = array_values(array_filter($pool, fn (string $phrase) => $phrase !== $last));
        $phrase = $fresh ? $fresh[array_rand($fresh)] : ($pool[0] ?? '');
        session(['terms.last_phrase' => $phrase]);

        return ['ok' => $ok, 'text' => strtr($phrase, [
            ':term' => (string) ($vars['term'] ?? ''),
            ':other' => (string) ($vars['other'] ?? ''),
            ':parent' => (string) ($vars['parent'] ?? ''),
            ':count' => (string) ($vars['count'] ?? ''),
        ])];
    }

    /**
     * Квадратики, неверный ответ. По порядку:
     *  1) введено название ДРУГОГО понятия из базы — говорим, как оно связано
     *     с нужным, и заметку преподавателя к этой паре, если она есть;
     *  2) до названия не хватило пары букв — «почти»;
     *  3) иначе просто называем понятие.
     * Правильное название ученик и так видит в клетках, поэтому в первых
     * двух случаях рыба его не повторяет.
     */
    private function sayAboutWrongName(Term $term, string $input): array
    {
        $matcher = AnswerMatcher::fromConfig();

        $other = Term::active()->where('id', '!=', $term->id)->get()
            ->first(fn (Term $candidate) => $matcher->matches($input, $candidate->acceptedNames()));

        if ($other) {
            $about = $this->relationTo($term, $other);

            // Заметка преподавателя точнее шаблона про связь — звучит вместо
            // него, а не вдобавок.
            if ($about['note'] !== '') {
                return ['ok' => false, 'text' => $this->say('name_other_none', false, $about['vars'])['text'].'. '.$about['note']];
            }

            return $this->say('name_other_'.($about['kind'] ?? 'none'), false, $about['vars']);
        }

        if ($matcher->isClose($input, $term->acceptedNames(), (int) config('terms.almost_max_edits'), (float) config('terms.almost_max_share'))) {
            return $this->say('name_almost', false);
        }

        return $this->say('name_wrong', false, ['term' => $this->nameOf($term)]);
    }

    /**
     * Квиз, неверный клик: чей это признак и — если понятия связаны или к
     * паре есть заметка преподавателя — почему их легко спутать.
     */
    private function sayAboutWrongFeature(Term $term, ?Term $owner): array
    {
        $say = $this->say('quiz_wrong', false, ['term' => $this->nameOf($owner)]);
        if (!$owner) {
            return $say;
        }

        $about = $this->relationTo($term, $owner);
        $tail = $about['note'];
        if ($tail === '' && in_array($about['kind'], [TermRelations::SIBLING, TermRelations::CONFUSABLE], true)) {
            $tail = $this->say('quiz_wrong_'.$about['kind'], false, $about['vars'])['text'];
        }

        return ['ok' => false, 'text' => rtrim(trim($say['text'].'. '.$tail), '. ')];
    }

    /**
     * Что база знает о паре понятий: связь (TermRelations) и заметка
     * преподавателя. Только то, что записано прямо, — «отличий» из списков
     * признаков здесь не выводим (см. комментарий в TermRelations).
     *
     * @return array{kind: ?string, vars: array, note: string}
     */
    private function relationTo(Term $term, Term $other): array
    {
        $parentOf = Term::query()->pluck('parent_id', 'id')->map(fn ($id) => $id === null ? null : (int) $id)->all();
        $confusable = $term->confusables()->pluck('terms.id')
            ->merge($other->confusables()->where('terms.id', $term->id)->exists() ? [$other->id] : [])
            ->map(fn ($id) => (int) $id)->all();

        $relation = (new TermRelations())->between($parentOf, (int) $term->id, (int) $other->id, $confusable);

        return [
            'kind' => $relation['kind'] ?? null,
            'vars' => [
                'term' => $this->nameOf($term),
                'other' => $this->nameOf($other),
                'parent' => isset($relation['parent']) ? $this->nameOf(Term::find($relation['parent'])) : null,
            ],
            // Заметка действует в обе стороны: записана у любого из двух.
            'note' => trim((string) (($term->pair_notes[$other->slug] ?? null) ?? ($other->pair_notes[$term->slug] ?? ''))),
        ];
    }

    /**
     * Название понятия для реплики рыбы — с уточнением, если оно есть: в базе
     * есть понятия с одинаковым названием («Экономика» как наука и как
     * хозяйство, две «Семьи», два «Общества»), и без уточнения реплика
     * «это признак понятия „Экономика“» не говорила бы, какого именно.
     */
    private function nameOf(?Term $term): string
    {
        if (!$term) {
            return '';
        }

        return $term->qualifier ? "{$term->title} ({$term->qualifier})" : $term->title;
    }

    /**
     * Была ли по этому термину ошибка в первой попытке этой сессии — то
     * есть сейчас он отвечен «со второго захода».
     */
    private function missedBefore(TermSession $session, array $state, int $termId, string $method): bool
    {
        $first = $session->kind === TermSession::KIND_LEARN
            ? ($state['first'][$method][$termId] ?? null)
            : ($state['first'][$termId] ?? null);

        return $first === false;
    }

    /**
     * Картинки рыбы ученика для сессии: спокойная, радостная, задумчивая
     * (на ошибку — именно задумчивая, грустной в тренажёре нет) и жующая —
     * для итога, когда заработанный корм летит к ней.
     */
    private function mascotData(Request $request): array
    {
        $level = $this->fish->levelFor((int) $request->user()->fish_total_fed);

        return [
            'default' => $this->fish->mascotImageUrl($level),
            'correct' => $this->fish->mascotImageUrl($level, 'correct'),
            'thinking' => $this->fish->mascotImageUrl($level, 'partly_correct'),
            'eating' => $this->fish->mascotImageUrl($level, 'eating'),
        ];
    }

    private function progressData(TermSession $session, array $state): array
    {
        $done = count($state['done'] ?? []);
        $isLearn = $session->kind === TermSession::KIND_LEARN;

        // Термин засчитывается в очередь «пройденных» только на «Далее» (или
        // автопереходе), но ученик видит успех сразу — счётчик должен
        // прибавиться в тот же момент, а не через пару секунд вместе с
        // улетающей карточкой. В изучении последним шагом термина бывает
        // только задание на признаки, в остальных сессиях — любое задание.
        $current = $state['current'] ?? null;
        $succeeded = match ($current['type'] ?? null) {
            'a' => !$isLearn && ($current['result']['correct'] ?? false),
            'b', 'c' => (bool) ($current['done'] ?? false),
            default => false,
        };
        if ($succeeded) {
            $done++;
        }
        $total = max(1, $isLearn ? count($state['terms']) : (int) $session->total);

        // Полоса и кольцо показывают ровно то, что написано в подписи:
        // выучено 1 из 5 — заполнено на пятую часть. Раньше в изучении
        // полоса считала ещё и промежуточные шаги (карточка, квадратики) и
        // расходилась с подписью: «1 из 5» при кольце почти наполовину.
        return [
            'progressLabel' => ($isLearn ? 'Выучено' : 'Пройдено')." {$done} из {$total}",
            'percent' => (int) round($done / $total * 100),
        ];
    }

    private function resultData(Request $request, TermSession $session): array
    {
        $user = $request->user();
        $state = $session->state;
        $first = $state['first'] ?? [];
        $isLearn = $session->kind === TermSession::KIND_LEARN;

        // Итог по терминам, а не по заданиям: в изучении у термина два
        // задания, и «с первой попытки» он только если оба без ошибок.
        if ($isLearn) {
            $termIds = $state['terms'] ?? [];
            $okOf = fn (int $id) => ($first['a'][$id] ?? true) !== false && ($first['b'][$id] ?? true) !== false;
        } else {
            $termIds = array_map('intval', array_keys($first));
            $okOf = fn (int $id) => ($first[$id] ?? true) !== false;
        }

        $terms = Term::whereIn('id', $termIds)->get()->keyBy('id');
        $dots = [];
        foreach ($termIds as $id) {
            if ($terms->has($id)) {
                $dots[] = ['title' => $this->nameOf($terms[$id]), 'ok' => $okOf((int) $id)];
            }
        }
        $failedIds = array_values(array_filter($termIds, fn ($id) => !$okOf((int) $id)));

        $say = match (true) {
            $session->kind === TermSession::KIND_FREE => 'result_free',
            $failedIds === [] => 'result_perfect',
            (int) $session->corm_awarded > 0 => 'result_good',
            default => 'result_hard',
        };

        // «Что дальше»: ближайшее повторение и вторая кнопка — после
        // свободной тренировки «Ещё круг», после остальных — свободная
        // тренировка, если она сейчас открыта.
        $overview = $this->trainer->overview($user);
        $freeOpen = in_array($overview['free']['status'], ['available', 'in_progress'], true);

        return [
            'resultSay' => $this->say($say, $failedIds === []),
            'dots' => $dots,
            'okCount' => count(array_filter($dots, fn ($d) => $d['ok'])),
            'failedTerms' => Term::whereIn('id', $failedIds)->orderBy('sort_order')->get(),
            'dueTomorrow' => $this->trainer->dueTomorrow($user),
            'nextReview' => $this->trainer->nextReview($user, now()->addDay()->toDateString()),
            'cormBalance' => (int) $user->fresh()->fish_corm_balance,
            'secondary' => $freeOpen
                ? ($session->kind === TermSession::KIND_FREE ? 'Ещё круг' : 'Свободная тренировка')
                : null,
        ];
    }
}
