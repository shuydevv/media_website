<?php

namespace App\Service\LiveChat;

use App\Models\ChatEmotion;
use App\Models\ChatMessage;
use App\Models\ChatMessageReaction;
use App\Models\ChatPoll;
use App\Models\ChatPollVote;
use App\Models\ChatReward;
use App\Models\CourseSession;
use App\Models\Homework;
use App\Models\Lesson;
use App\Models\User;
use Carbon\Carbon;

/**
 * Итоги урока — карточка, которую админ отправляет в чат (обычно в конце
 * эфира). Блоки: общее, «Пон / Не пон», главная эмоция, самый жаркий момент,
 * корм, герои урока, что дальше; плюс личная строка — её каждый ученик видит
 * свою (считается при показе, в карточке не хранится). Публично — только
 * хорошее: кто «не пон», в итоги не попадает.
 */
class LiveChatSummary
{
    public const BLOCKS = ['general', 'polls', 'emotion', 'hot', 'corm', 'heroes', 'next', 'me'];

    public function __construct(private LiveChatService $chat)
    {
    }

    /** Все блоки (для предпросмотра в пульте). Пустые — null. */
    public function build(Lesson $lesson): array
    {
        $msgs = ChatMessage::where('lesson_id', $lesson->id)->whereNull('deleted_at')->whereNull('card')
            ->get(['id', 'user_id', 'author_name', 'is_question', 'answered_at', 'thread_id', 'created_at']);
        $team = $this->teamIds($msgs->pluck('user_id'));
        $students = $msgs->reject(fn ($m) => isset($team[$m->user_id]));

        return [
            'general' => $this->general($lesson, $msgs),
            'polls' => $this->polls($lesson),
            'emotion' => $this->emotion($lesson),
            'hot' => $this->hot($lesson, $students),
            'corm' => $this->corm($lesson),
            'heroes' => $this->heroes($lesson, $students, $team),
            'next' => $this->next($lesson, $msgs),
        ];
    }

    /** Отправить в чат: выбранные блоки + пара слов от преподавателя. */
    public function send(Lesson $lesson, User $by, array $blocks, ?string $note): ChatMessage
    {
        $blocks = array_values(array_intersect(self::BLOCKS, $blocks));
        $data = array_intersect_key($this->build($lesson), array_flip($blocks));
        $note = trim((string) $note) ?: null;
        $card = ['type' => 'summary', 'blocks' => $data, 'note' => $note, 'me' => in_array('me', $blocks, true)];

        return $this->chat->mutate($this->chat->chatFor($lesson), fn ($v) => ChatMessage::create([
            'lesson_id' => $lesson->id,
            'user_id' => $by->id,
            'author_name' => $this->chat->teamName($by),
            'author_badge' => $this->chat->teamBadge($by),
            'body' => $note ?? 'Итоги урока',
            'card' => $card,
            'version' => $v,
        ]));
    }

    /** Личная строка ученика: сообщения, верные ответы, корм, «пон». */
    public function personal(int $lessonId, User $u): array
    {
        $votes = ChatPollVote::whereIn('poll_id', ChatPoll::where('lesson_id', $lessonId)->pluck('id'))
            ->where('user_id', $u->id)->get(['choice']);

        return [
            'msgs' => ChatMessage::where('lesson_id', $lessonId)->where('user_id', $u->id)->whereNull('deleted_at')->whereNull('card')->count(),
            'answers' => ChatReward::where('lesson_id', $lessonId)->where('user_id', $u->id)->count(),
            'corm' => (int) ChatReward::where('lesson_id', $lessonId)->where('user_id', $u->id)->sum('corm'),
            'yes' => $votes->where('choice', true)->count(),
            'votes' => $votes->count(),
        ];
    }

    // ── Блоки ─────────────────────────────────────────────────────────

    private function general(Lesson $lesson, $msgs): array
    {
        $min = 0;
        foreach ($this->chat->segments($lesson->id) as [$from, $to]) {
            $min += $from->diffInMinutes($to ?? now());
        }
        if (! $min && $msgs->count() > 1) {
            $min = $msgs->min('created_at')->diffInMinutes($msgs->max('created_at'));
        }
        $roots = $msgs->whereNull('thread_id');

        return [
            'min' => $min ?: null,
            'peak' => (int) $this->chat->chatFor($lesson)->peak_online,
            'msgs' => $msgs->count(),
            'qs' => $roots->where('is_question', true)->count(),
            'ans' => $roots->where('is_question', true)->whereNotNull('answered_at')->count(),
        ];
    }

    private function polls(Lesson $lesson): ?array
    {
        $polls = ChatPoll::where('lesson_id', $lesson->id)->with('votes:id,poll_id,choice')->orderBy('id')->get();
        $rated = $polls->map(fn ($p) => ['at' => $p->created_at->timestamp, 'n' => $p->votes->count(), 'yes' => $p->votes->where('choice', true)->count()])
            ->where('n', '>', 0)->values();
        if ($rated->isEmpty()) {
            return null;
        }
        $worst = $rated->where('n', '>=', 3)->sortByDesc(fn ($p) => ($p['n'] - $p['yes']) / $p['n'])->first();

        return [
            'n' => $rated->count(),
            'yes' => (int) round($rated->avg(fn ($p) => $p['yes'] / $p['n'] * 100)),
            'worst' => $worst && $worst['yes'] < $worst['n']
                ? ['at' => $worst['at'], 'no' => (int) round(($worst['n'] - $worst['yes']) / $worst['n'] * 100)]
                : null,
        ];
    }

    private function emotion(Lesson $lesson): ?array
    {
        $byCode = ChatEmotion::where('lesson_id', $lesson->id)->whereNull('withdrawn_at')->withCount('supports')->get()
            ->groupBy('code')->map(fn ($g) => $g->count() + $g->sum('supports_count'))->sortDesc();
        if ($byCode->isEmpty()) {
            return null;
        }
        $code = $byCode->keys()->first();
        $info = collect(app(LiveChatRoom::class)->emotionCatalog())->firstWhere('code', $code);

        return ['code' => $code, 'e' => $info['e'] ?? '❔', 'label' => $info['label'] ?? $code, 'img' => $info['img'] ?? null, 'c' => (int) $byCode->first()];
    }

    /** Минута, когда чат «взорвался»: больше всего сообщений и эмоций учеников. */
    private function hot(Lesson $lesson, $students): ?array
    {
        $minutes = [];
        foreach ($students as $m) {
            $k = intdiv($m->created_at->timestamp, 60);
            $minutes[$k] = ($minutes[$k] ?? 0) + 1;
        }
        foreach (ChatEmotion::where('lesson_id', $lesson->id)->whereNull('withdrawn_at')->withCount('supports')->get() as $e) {
            $k = intdiv($e->created_at->timestamp, 60);
            $minutes[$k] = ($minutes[$k] ?? 0) + 1 + $e->supports_count;
        }
        if (! $minutes) {
            return null;
        }
        arsort($minutes);
        $k = array_key_first($minutes);

        return $minutes[$k] >= 3 ? ['at' => $k * 60, 'c' => $minutes[$k]] : null;
    }

    private function corm(Lesson $lesson): ?array
    {
        $rows = ChatReward::where('lesson_id', $lesson->id)->get(['corm']);

        return $rows->isEmpty() ? null : ['c' => (int) $rows->sum('corm'), 'n' => $rows->count()];
    }

    private function heroes(Lesson $lesson, $students, array $team): ?array
    {
        $names = $students->sortBy('id')->groupBy('user_id')->map(fn ($g) => $g->last()->author_name);
        $name = fn ($uid) => $names[$uid] ?? ChatMessage::where('lesson_id', $lesson->id)->where('user_id', $uid)->latest('id')->value('author_name');

        $rewards = ChatReward::where('lesson_id', $lesson->id)->orderBy('message_id')->get(['user_id', 'corm', 'message_id']);
        $top = $rewards->groupBy('user_id')->map(fn ($g) => (int) $g->sum('corm'))->sortDesc()->take(3)
            ->map(fn ($c, $uid) => ['n' => $name($uid), 'c' => $c])->values()->all();
        $first = $rewards->first();

        $curious = $students->whereNull('thread_id')->where('is_question', true)->groupBy('user_id')->map->count()->sortDesc();
        $cUid = $curious->keys()->first();

        // «Душа чата» — больше всего реакций на свои сообщения (свои реакции не в счёт).
        $byMsg = $students->pluck('user_id', 'id');
        $soul = [];
        if ($byMsg->isNotEmpty()) {
            foreach (ChatMessageReaction::whereIn('message_id', $byMsg->keys())->get(['message_id', 'user_id']) as $r) {
                $author = $byMsg[$r->message_id];
                if ((int) $r->user_id !== (int) $author) {
                    $soul[$author] = ($soul[$author] ?? 0) + 1;
                }
            }
        }
        arsort($soul);
        $sUid = array_key_first($soul);

        $out = [
            'top' => $top,
            'first' => $first ? ['n' => $name($first->user_id)] : null,
            'curious' => $cUid ? ['n' => $name($cUid), 'c' => $curious->first()] : null,
            'soul' => $sUid && $soul[$sUid] >= 2 ? ['n' => $name($sUid), 'c' => $soul[$sUid]] : null,
        ];

        return array_filter($out) ? $out : null;
    }

    private function next(Lesson $lesson, $msgs): ?array
    {
        $hw = Homework::where('lesson_id', $lesson->id)->orderBy('id')->first();
        $session = $lesson->courseSession;
        $nextLesson = null;
        if ($session && $session->date) {
            $date = Carbon::parse($session->date)->toDateString();
            $next = CourseSession::visible()->where('course_id', $session->course_id)
                ->where(fn ($q) => $q->where('date', '>', $date)
                    ->orWhere(fn ($q) => $q->where('date', $date)->where('start_time', '>', $session->start_time)))
                ->orderBy('date')->orderBy('start_time')->first();
            if ($next) {
                $nextLesson = ['ts' => Carbon::parse(Carbon::parse($next->date)->toDateString().' '.($next->start_time ?: '00:00'))->timestamp];
            }
        }
        $open = $msgs->whereNull('thread_id')->where('is_question', true)->whereNull('answered_at')->count();
        $out = [
            'hw' => $hw ? ['title' => $hw->title, 'due' => $hw->due_at?->timestamp, 'url' => route('student.submissions.create', $hw)] : null,
            'lesson' => $nextLesson,
            'open' => $open,
        ];

        return $hw || $nextLesson || $open ? $out : null;
    }

    /** @return array<int, true> id пользователей команды среди авторов */
    private function teamIds($uids): array
    {
        return User::whereIn('id', $uids->unique()->filter(fn ($id) => $id < LiveChatService::GUEST_BASE))
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_MENTOR])->pluck('id')->flip()->map(fn () => true)->all();
    }
}
