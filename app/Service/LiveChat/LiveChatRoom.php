<?php

namespace App\Service\LiveChat;

use App\Models\ChatEmotion;
use App\Models\ChatEmotionSupport;
use App\Models\ChatMessage;
use App\Models\ChatPoll;
use App\Models\ChatPollVote;
use App\Models\Lesson;
use App\Models\LessonChat;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Пульт эфира и то, что висит над лентой: эмоции учеников, быстрый опрос
 * «Пон / Не пон», перерыв и тишина, онлайн, итоги урока.
 *
 * Каждое изменение — через LiveChatService::mutate(): растёт версия чата, и
 * браузеры видят его следующим опросом ленты (feed() кладёт snapshot()).
 */
class LiveChatRoom
{
    private array $cfg;

    public function __construct(private LiveChatService $chat)
    {
        $this->cfg = config('live_chat');
    }

    // ── Для браузера ──────────────────────────────────────────────────

    /** Набор эмоций: code → эмодзи, подпись, картинка. */
    public function emotionCatalog(): array
    {
        $out = [];
        foreach ($this->cfg['emotions'] as $code => [$emoji, $label, $noto]) {
            $out[] = ['code' => $code, 'e' => $emoji, 'label' => $label, 'img' => asset('img/chat-reactions/noto/'.$noto.'.svg')];
        }

        return $out;
    }

    /** Что висит над лентой и состояние пульта — для каждого изменения ленты. */
    public function snapshot(Lesson $lesson, LessonChat $chat, User $viewer): array
    {
        return [
            'room' => [
                'breakUntil' => $chat->break_until?->isFuture() ? $chat->break_until->timestamp : null,
                'quietUntil' => $chat->quiet_until?->isFuture() ? $chat->quiet_until->timestamp : null,
            ],
            'emo' => $this->activeEmotions($lesson, $viewer),
            'poll' => $this->currentPoll($lesson, $viewer),
        ];
    }

    private function activeEmotions(Lesson $lesson, User $viewer): array
    {
        return ChatEmotion::where('lesson_id', $lesson->id)->where('ends_at', '>', now())
            ->orderBy('id')->with('supports')->get()
            ->map(fn (ChatEmotion $e) => [
                'id' => $e->id,
                'code' => $e->code,
                'n' => $e->author_name,
                'uid' => (int) $e->user_id,
                'ends' => $e->ends_at->timestamp,
                'c' => $e->supports->count(),
                'mine' => (int) $e->user_id === (int) $viewer->id || $e->supports->contains('user_id', $viewer->id),
                'names' => $e->supports->sortByDesc('id')->take(30)->pluck('supporter_name')->values(),
            ])->values()->all();
    }

    private function latestPoll(Lesson $lesson): ?ChatPoll
    {
        return ChatPoll::where('lesson_id', $lesson->id)->orderByDesc('id')->first();
    }

    /** Идущий опрос или только что закончившийся (итог висит ещё немного). */
    private function currentPoll(Lesson $lesson, User $viewer): ?array
    {
        $p = $this->latestPoll($lesson);
        if (! $p) {
            return null;
        }
        $end = $p->closed_at && $p->closed_at->lt($p->ends_at) ? $p->closed_at : $p->ends_at;
        if (! $p->isActive() && $end->copy()->addSeconds($this->cfg['poll_result_seconds'])->isPast()) {
            return null;
        }

        return $this->pollOut($p, $viewer) + ['hideAt' => $end->copy()->addSeconds($this->cfg['poll_result_seconds'])->timestamp];
    }

    private function pollOut(ChatPoll $p, ?User $viewer): array
    {
        $votes = $p->votes()->orderBy('id')->get();
        $mine = $viewer ? $votes->firstWhere('user_id', $viewer->id) : null;

        return [
            'id' => $p->id,
            'ends' => ($p->closed_at && $p->closed_at->lt($p->ends_at) ? $p->closed_at : $p->ends_at)->timestamp,
            'active' => $p->isActive(),
            'yes' => $votes->where('choice', true)->count(),
            'no' => $votes->where('choice', false)->count(),
            'mine' => $mine ? (bool) $mine->choice : null,
            'yesNames' => $votes->where('choice', true)->pluck('voter_name')->values(),
            'noNames' => $votes->where('choice', false)->pluck('voter_name')->values(),
            'at' => $p->created_at->timestamp,
        ];
    }

    // ── Эмоции ────────────────────────────────────────────────────────

    /**
     * Поделиться эмоцией. Такая же уже висит — это поддержка, а не новая.
     *
     * @throws ChatRefused
     */
    public function proposeEmotion(Lesson $lesson, User $u, string $code): void
    {
        if (! isset($this->cfg['emotions'][$code])) {
            throw new ChatRefused('emotion', 'Такой эмоции нет');
        }
        $this->assertCanAct($lesson, $u);
        if (LiveChatService::isTeam($u) || LiveChatService::isGuest($u)) {
            throw new ChatRefused('emotion', 'Эмоции — для учеников');
        }
        if ($this->chat->level($u) < $this->cfg['emotion_level']) {
            throw new ChatRefused('level', 'Делиться эмоциями можно с '.$this->cfg['emotion_level'].' уровня акулы');
        }
        $poll = $this->latestPoll($lesson);
        if ($poll && $poll->isActive()) {
            throw new ChatRefused('poll', 'Сейчас идёт опрос — эмоции после него');
        }

        $active = ChatEmotion::where('lesson_id', $lesson->id)->where('ends_at', '>', now())->get();
        $same = $active->firstWhere('code', $code);
        if ($same) {
            $this->supportEmotion($lesson, $u, $same->id);

            return;
        }
        if ($active->count() >= $this->cfg['emotion_max']) {
            throw new ChatRefused('full', 'Максимум эмоций на экране');
        }
        $last = ChatEmotion::where('lesson_id', $lesson->id)->where('user_id', $u->id)->max('created_at');
        if ($last) {
            $wait = \Carbon\Carbon::parse($last)->addSeconds($this->cfg['emotion_cooldown'])->diffInSeconds(now(), false);
            if ($wait < 0) {
                throw new ChatRefused('cooldown', 'Следующей эмоцией можно поделиться через '.$this->clock(-$wait), ['wait' => -$wait]);
            }
        }

        $chat = $this->chat->chatFor($lesson);
        $this->chat->mutate($chat, fn () => ChatEmotion::create([
            'lesson_id' => $lesson->id,
            'user_id' => $u->id,
            'author_name' => $this->chat->displayName($u),
            'code' => $code,
            'ends_at' => now()->addSeconds($this->cfg['emotion_seconds']),
        ]));
    }

    /** @throws ChatRefused */
    public function supportEmotion(Lesson $lesson, User $u, int $id): void
    {
        $this->assertCanAct($lesson, $u);
        $e = ChatEmotion::where('lesson_id', $lesson->id)->where('ends_at', '>', now())->find($id);
        if (! $e) {
            throw new ChatRefused('gone', 'Эмоция уже исчезла');
        }
        if ((int) $e->user_id === (int) $u->id) {
            return; // свою не поддерживают — она и так твоя
        }
        $this->chat->mutate($this->chat->chatFor($lesson), fn () => ChatEmotionSupport::firstOrCreate(
            ['emotion_id' => $e->id, 'user_id' => $u->id],
            ['supporter_name' => $this->chat->displayName($u)],
        ));
    }

    // ── «Пон / Не пон» ────────────────────────────────────────────────

    public function startPoll(Lesson $lesson, User $by): void
    {
        $chat = $this->chat->chatFor($lesson);
        $this->chat->mutate($chat, function () use ($lesson, $by) {
            ChatPoll::where('lesson_id', $lesson->id)->whereNull('closed_at')->update(['closed_at' => now()]);
            // Опрос важнее эмоций: висящие эмоции снимаются.
            ChatEmotion::where('lesson_id', $lesson->id)->where('ends_at', '>', now())->update(['ends_at' => now()]);
            ChatPoll::create(['lesson_id' => $lesson->id, 'created_by' => $by->id, 'ends_at' => now()->addSeconds($this->cfg['poll_seconds'])]);
        });
    }

    public function closePoll(Lesson $lesson): void
    {
        $this->chat->mutate($this->chat->chatFor($lesson), fn () => ChatPoll::where('lesson_id', $lesson->id)
            ->whereNull('closed_at')->update(['closed_at' => now()]));
    }

    /** @throws ChatRefused */
    public function vote(Lesson $lesson, User $u, bool $choice): void
    {
        if ($u->is_demo) {
            throw new ChatRefused('demo', 'В демо-режиме голосовать нельзя');
        }
        $p = $this->latestPoll($lesson);
        if (! $p || ! $p->isActive()) {
            throw new ChatRefused('poll', 'Опрос уже закончился');
        }
        if (ChatPollVote::where('poll_id', $p->id)->where('user_id', $u->id)->exists()) {
            throw new ChatRefused('voted', 'Вы уже ответили');
        }
        $this->chat->mutate($this->chat->chatFor($lesson), fn () => ChatPollVote::create([
            'poll_id' => $p->id, 'user_id' => $u->id, 'voter_name' => $this->chat->displayName($u), 'choice' => $choice,
        ]));
    }

    // ── Перерыв и тишина ──────────────────────────────────────────────

    /** $minutes = 0 — закончить. */
    public function setBreak(Lesson $lesson, int $minutes): void
    {
        $this->chat->mutate($this->chat->chatFor($lesson), function ($v, $row) use ($minutes) {
            $row->break_until = $minutes > 0 ? now()->addMinutes($minutes) : null;
        });
    }

    /** Тишина: ученики могут только задавать вопросы. $minutes = 0 — закончить. */
    public function setQuiet(Lesson $lesson, int $minutes): void
    {
        $this->chat->mutate($this->chat->chatFor($lesson), function ($v, $row) use ($minutes) {
            $row->quiet_until = $minutes > 0 ? now()->addMinutes($minutes) : null;
        });
    }

    // ── Онлайн и итоги ────────────────────────────────────────────────

    public function touch(Lesson $lesson, User $u): void
    {
        DB::table('chat_presence')->upsert(
            [['lesson_id' => $lesson->id, 'user_id' => $u->id, 'seen_at' => now()]],
            ['lesson_id', 'user_id'],
            ['seen_at'],
        );
    }

    public function online(Lesson $lesson): int
    {
        return DB::table('chat_presence')->where('lesson_id', $lesson->id)
            ->where('seen_at', '>', now()->subSeconds($this->cfg['presence_seconds']))->count();
    }

    /** Итоги урока для пульта: активные ученики, опросы, эмоции, вопросы. */
    public function stats(Lesson $lesson): array
    {
        $msgs = ChatMessage::where('lesson_id', $lesson->id)->whereNull('deleted_at');
        $top = (clone $msgs)->selectRaw('user_id, max(author_name) as name, count(*) as c')
            ->groupBy('user_id')->orderByDesc('c')->limit(8)->get()
            ->filter(fn ($r) => ! ($r->user_id < LiveChatService::GUEST_BASE && ($u = User::find($r->user_id)) && LiveChatService::isTeam($u)))
            ->take(5)->map(fn ($r) => ['name' => $r->name, 'c' => (int) $r->c])->values();

        $catalog = collect($this->emotionCatalog())->keyBy('code');
        $emotions = ChatEmotion::where('lesson_id', $lesson->id)->withCount('supports')->orderByDesc('id')->limit(30)->get()
            ->map(fn (ChatEmotion $e) => [
                'e' => $catalog[$e->code]['e'] ?? '❔',
                'label' => $catalog[$e->code]['label'] ?? $e->code,
                'n' => $e->author_name,
                'c' => (int) $e->supports_count,
                'at' => $e->created_at->timestamp,
            ]);

        $polls = ChatPoll::where('lesson_id', $lesson->id)->orderByDesc('id')->limit(20)->get()
            ->map(fn (ChatPoll $p) => $this->pollOut($p, null));

        return [
            'online' => $this->online($lesson),
            'messages' => (clone $msgs)->count(),
            'questions' => (clone $msgs)->whereNull('thread_id')->where('is_question', true)->count(),
            'unanswered' => (clone $msgs)->whereNull('thread_id')->where('is_question', true)->whereNull('answered_at')->count(),
            'people' => (clone $msgs)->distinct()->count('user_id'),
            'top' => $top,
            'emotions' => $emotions,
            'polls' => $polls,
        ];
    }

    // ── Служебное ─────────────────────────────────────────────────────

    /** @throws ChatRefused */
    private function assertCanAct(Lesson $lesson, User $u): void
    {
        if ($u->is_demo) {
            throw new ChatRefused('demo', 'В демо-режиме это недоступно');
        }
        $chat = $this->chat->chatFor($lesson);
        if (! ChatState::canWrite($this->chat->state($lesson, $chat)['state'])) {
            throw new ChatRefused('closed', 'Чат закрыт');
        }
    }

    private function clock(int $sec): string
    {
        return intdiv($sec, 60).':'.str_pad((string) ($sec % 60), 2, '0', STR_PAD_LEFT);
    }
}
