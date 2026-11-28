<?php

namespace App\Service\LiveChat;

use App\Models\ChatEmotion;
use App\Models\ChatGuest;
use App\Models\ChatMessage;
use App\Models\ChatMessageReaction;
use App\Models\ChatMute;
use App\Models\ChatReactionType;
use App\Models\Lesson;
use App\Models\LessonChat;
use App\Models\LessonLiveSegment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Чат трансляции урока: отправка, реакции, модерация, лента изменений.
 *
 * Обмен — опрос сервера (без WebSocket): браузер спрашивает «что изменилось
 * после версии N» (feed()). Каждое изменение идёт через change(): под
 * блокировкой строки lesson_chats номер версии растёт и проставляется
 * изменённому сообщению В ТОЙ ЖЕ транзакции — иначе опрос мог бы увидеть
 * новую версию чата раньше самого сообщения и навсегда его пропустить.
 */
class LiveChatService
{
    /** Сколько разных реакций ученик может поставить на одно сообщение. */
    public const MAX_REACTIONS = 3;

    /**
     * Гость по ссылке — не пользователь сайта: в чате его id = GUEST_BASE +
     * chat_guests.id (см. миграцию create_live_chat_room_tables).
     */
    public const GUEST_BASE = 1000000000;

    private array $cfg;

    public function __construct()
    {
        $this->cfg = config('live_chat');
    }

    // ── Чат, состояние ────────────────────────────────────────────────

    public function chatFor(Lesson $lesson): LessonChat
    {
        return LessonChat::firstOrCreate(['lesson_id' => $lesson->id]);
    }

    /** @return array<int, array{0: Carbon, 1: ?Carbon}> */
    public function segments(int $lessonId): array
    {
        return LessonLiveSegment::where('lesson_id', $lessonId)->orderBy('started_at')->get()
            ->map(fn ($s) => [$s->started_at, $s->ended_at])->all();
    }

    /**
     * Состояние чата сейчас. Закрытие «запоминается» (closed_at) лениво —
     * при первом же запросе после него, без крона: дальше не пересчитывать
     * страховочные сроки и не открыть чат обратно без нового эфира.
     *
     * @return array{state: string, opens_at: ?Carbon, break_until: ?Carbon}
     */
    public function state(Lesson $lesson, LessonChat $chat, ?array $segments = null): array
    {
        $segments ??= $this->segments($lesson->id);
        $session = $lesson->courseSession;
        $start = $session?->date ? $session->startDateTime() : null;
        $end = $session?->date && $session->end_time ? $session->endDateTime() : $start?->copy()->addHours(2);
        // Урок через полночь: время конца без даты — раньше начала.
        if ($start && $end && $end->lt($start)) {
            $end = $end->copy()->addDay();
        }

        $res = ChatState::resolve(now(), $start, $end, $segments, $chat->closed_at, $this->cfg);

        $liveNow = collect($segments)->contains(fn ($s) => $s[1] === null);
        if ($res['state'] === ChatState::CLOSED && ! $chat->closed_at && ! $liveNow) {
            $chat->forceFill(['closed_at' => now()])->save();
        }

        return $res;
    }

    // ── Кто есть кто ──────────────────────────────────────────────────

    public static function isTeam(User $u): bool
    {
        return $u->isAdmin() || $u->isMentor();
    }

    public static function isGuest(User $u): bool
    {
        return (int) $u->id >= self::GUEST_BASE;
    }

    /** Гость как «пользователь» в памяти — не сохраняется в users. */
    public static function guestUser(ChatGuest $g): User
    {
        $u = new User();
        $u->forceFill([
            'id' => self::GUEST_BASE + $g->id,
            'role' => User::ROLE_READER,
            'chat_name' => $g->name,
            'is_demo' => false,
        ]);

        return $u;
    }

    public static function guestFromId(int $id): ?User
    {
        $g = ChatGuest::find($id - self::GUEST_BASE);

        return $g ? self::guestUser($g) : null;
    }

    /** Уровень акулы ученика (от него зависят эмоции и эмодзи в имени). */
    public function level(User $u): int
    {
        if (self::isGuest($u) || self::isTeam($u)) {
            return 0;
        }

        return app(\App\Service\FishFoodService::class)->levelFor((int) $u->fish_total_fed);
    }

    /** Имя, под которым человек виден в чате. */
    public function displayName(User $u): string
    {
        return self::isTeam($u) ? $this->teamName($u) : $this->studentName($u);
    }

    /** Эмодзи в имени — с уровня emoji_name_level; ниже — убираются. */
    public function stripEmoji(string $s): string
    {
        $s = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2300}-\x{23FF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}\x{20E3}\x{3030}\x{303D}\x{3297}\x{3299}\x{E0020}-\x{E007F}]/u', '', $s);

        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    /**
     * Имя ученика по умолчанию: последнее использованное, иначе из профиля —
     * имя и первая буква фамилии с точкой («Аня К.»).
     */
    public function studentName(User $u): string
    {
        $profile = trim((string) $u->first_name);
        if ($profile !== '' && trim((string) $u->last_name) !== '') {
            $profile .= ' '.mb_strtoupper(mb_substr(trim($u->last_name), 0, 1)).'.';
        }
        $name = $u->chat_name ?: ($profile ?: $u->name);

        return Str::limit(trim((string) $name) ?: (self::isGuest($u) ? 'Гость' : 'Ученик'), $this->cfg['name_max'], '');
    }

    public function teamName(User $u): string
    {
        return $u->chat_name ?: (trim($u->full_name) ?: $u->name);
    }

    public function teamBadge(User $u): string
    {
        return $u->chat_badge ?: ($u->isAdmin() ? 'Команда' : 'Куратор');
    }

    public function activeMute(User $u, Lesson $lesson): ?ChatMute
    {
        if (self::isTeam($u)) {
            return null;
        }

        return ChatMute::activeFor($u->id, $lesson->id)
            ->orderByRaw('until is null desc')->orderByDesc('until')->first();
    }

    /** Что браузер знает о самом пользователе. */
    public function me(User $u, Lesson $lesson, string $state): array
    {
        $team = self::isTeam($u);
        $mute = $this->activeMute($u, $lesson);

        $level = $this->level($u);
        $guest = self::isGuest($u);
        $lastEmotion = $team || $guest ? null : ChatEmotion::where('lesson_id', $lesson->id)->where('user_id', $u->id)->max('created_at');
        $emotionAt = $lastEmotion ? Carbon::parse($lastEmotion)->addSeconds($this->cfg['emotion_cooldown']) : null;

        return [
            'id' => (int) $u->id,
            'name' => $team ? $this->teamName($u) : $this->studentName($u),
            'badge' => $team ? $this->teamBadge($u) : ($guest ? 'Гость' : null),
            'team' => $team,
            'admin' => $u->isAdmin(),
            'guest' => $guest,
            'demo' => (bool) $u->is_demo,
            'level' => $level,
            'emotion' => ! $team && ! $guest && $level >= $this->cfg['emotion_level'],
            'emotionAt' => $emotionAt && $emotionAt->isFuture() ? $emotionAt->timestamp : null,
            'emojiName' => $level >= $this->cfg['emoji_name_level'],
            'muted' => $mute ? ['until' => $mute->until?->timestamp, 'lesson' => $mute->lesson_id !== null] : null,
            'write' => ChatState::canWrite($state) && ! $mute && ! $u->is_demo,
        ];
    }

    // ── Изменения ─────────────────────────────────────────────────────

    /** Изменение чата из соседних сервисов (LiveChatRoom) — тот же change(). */
    public function mutate(LessonChat $chat, callable $apply)
    {
        return $this->change($chat, $apply);
    }

    /** Один шаг изменения чата: $apply получает новый номер версии. */
    private function change(LessonChat $chat, callable $apply)
    {
        return DB::transaction(function () use ($chat, $apply) {
            $row = LessonChat::whereKey($chat->id)->lockForUpdate()->first();
            $v = $row->version + 1;
            $result = $apply($v, $row);
            $row->version = $v;
            $row->save();
            $chat->setRawAttributes($row->getAttributes(), true);

            return $result;
        });
    }

    /**
     * @throws ChatRefused
     */
    public function post(Lesson $lesson, User $u, string $text, ?int $replyTo, bool $question, ?int $threadId = null): array
    {
        $text = trim(preg_replace("/\r\n?/", "\n", $text));
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        if ($text === '') {
            throw new ChatRefused('empty', 'Пустое сообщение');
        }
        if (mb_strlen($text) > $this->cfg['message_max']) {
            throw new ChatRefused('long', 'Сообщение длиннее '.$this->cfg['message_max'].' символов');
        }

        $chat = $this->chatFor($lesson);
        $state = $this->state($lesson, $chat)['state'];
        $this->assertCanWrite($u, $lesson, $state);

        $team = self::isTeam($u);
        if (! $team && $chat->quiet_until && $chat->quiet_until->isFuture() && (! $question || $threadId || $replyTo)) {
            throw new ChatRefused('quiet', 'Сейчас тишина — можно только задать вопрос преподавателю');
        }
        $slow = ['slow' => false, 'wait' => 0];
        if (! $team) {
            $recent = ChatMessage::where('lesson_id', $lesson->id)->where('user_id', $u->id)
                ->where('created_at', '>', now()->subSeconds($this->cfg['flood']['calm_seconds'] + 60))
                ->orderBy('id')->get(['created_at', 'body'])
                ->map(fn ($m) => [$m->created_at->timestamp, $m->body])->all();
            $check = FloodGuard::check($recent, $text, now()->timestamp, $this->cfg['flood']);
            if (! $check['allowed']) {
                throw new ChatRefused('slow', 'Не так быстро', ['wait' => $check['wait']]);
            }
            $slow = ['slow' => $check['slow'], 'wait' => $check['wait']];
        }

        // Ответы живут в ветке исходного сообщения (thread_id), а не в общей
        // ленте. Цитата (reply_to_id) — только на сообщение внутри той же ветки.
        $live = fn () => ChatMessage::where('lesson_id', $lesson->id)->whereNull('deleted_at');
        $root = $threadId ? $live()->whereNull('thread_id')->find($threadId) : null;
        if ($threadId && ! $root) {
            throw new ChatRefused('thread', 'Сообщение, на которое вы отвечаете, удалено');
        }
        $reply = $replyTo ? $live()->find($replyTo) : null;
        if ($reply && ! $root) {
            $root = $reply->thread_id ? $live()->find($reply->thread_id) : $reply;
        }
        if ($reply && $root && ($reply->id === $root->id || (int) $reply->thread_id !== $root->id)) {
            $reply = null;
        }

        $msg = $this->change($chat, function ($v) use ($lesson, $u, $team, $text, $reply, $root, $question) {
            // У исходного сообщения меняется счётчик ответов — оно тоже «изменилось».
            // Команда ответила в ветке вопроса — у вопроса сама ставится «есть ответ».
            if ($root) {
                $fill = ['version' => $v];
                if ($team && $root->is_question && ! $root->answered_at) {
                    $fill += ['answered_at' => now(), 'answered_by' => $u->id];
                }
                $root->forceFill($fill)->save();
            }

            return ChatMessage::create([
                'lesson_id' => $lesson->id,
                'user_id' => $u->id,
                'author_name' => $team ? $this->teamName($u) : $this->studentName($u),
                'author_badge' => $team ? $this->teamBadge($u) : (self::isGuest($u) ? 'Гость' : null),
                'body' => $text,
                'reply_to_id' => $reply?->id,
                'thread_id' => $root?->id,
                'is_question' => $question && ! $team && ! $root,
                'version' => $v,
            ]);
        });

        return ['message' => $msg, 'slow' => $slow];
    }

    /** @throws ChatRefused */
    private function assertCanWrite(User $u, Lesson $lesson, string $state): void
    {
        if ($u->is_demo) {
            throw new ChatRefused('demo', 'В демо-режиме писать в чат нельзя');
        }
        if (! ChatState::canWrite($state)) {
            throw new ChatRefused('closed', 'Чат закрыт');
        }
        if ($this->activeMute($u, $lesson)) {
            throw new ChatRefused('muted', 'Вы не можете писать в этом чате');
        }
    }

    public function rename(User $u, string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        if ($this->level($u) < $this->cfg['emoji_name_level']) {
            $name = $this->stripEmoji($name);
        }
        $name = Str::limit($name, $this->cfg['name_max'], '');
        if ($name === '') {
            throw new ChatRefused('empty', 'Введите имя');
        }
        if (self::isGuest($u)) {
            ChatGuest::whereKey((int) $u->id - self::GUEST_BASE)->update(['name' => $name]);
            $u->chat_name = $name;
        } else {
            $u->forceFill(['chat_name' => $name])->save();
        }

        return $name;
    }

    /** Поставить или снять реакцию (повторное нажатие снимает). @throws ChatRefused */
    public function toggleReaction(Lesson $lesson, ChatMessage $msg, User $u, int $typeId): void
    {
        if ($u->is_demo) {
            throw new ChatRefused('demo', 'В демо-режиме реакции не ставятся');
        }
        $chat = $this->chatFor($lesson);
        if (! ChatState::canWrite($this->state($lesson, $chat)['state'])) {
            throw new ChatRefused('closed', 'Чат закрыт');
        }
        if ($msg->deleted_at) {
            throw new ChatRefused('deleted', 'Сообщение удалено');
        }
        // Выключенную реакцию новой поставить нельзя, снять свою — можно.
        $existing = ChatMessageReaction::where(['message_id' => $msg->id, 'user_id' => $u->id, 'reaction_type_id' => $typeId])->first();
        if (! $existing && ! ChatReactionType::where('id', $typeId)->where('is_active', true)->exists()) {
            throw new ChatRefused('reaction', 'Такой реакции нет');
        }

        $this->change($chat, function ($v) use ($msg, $u, $typeId, $existing) {
            if ($existing) {
                $existing->delete();
            } else {
                // Ученик — не больше 3 реакций на сообщение: четвёртая вытесняет
                // самую раннюю из его реакций.
                if (! self::isTeam($u)) {
                    $mine = ChatMessageReaction::where(['message_id' => $msg->id, 'user_id' => $u->id])->orderBy('id')->get();
                    $mine->take(max(0, $mine->count() - (self::MAX_REACTIONS - 1)))->each->delete();
                }
                ChatMessageReaction::firstOrCreate(['message_id' => $msg->id, 'user_id' => $u->id, 'reaction_type_id' => $typeId]);
            }
            $msg->forceFill(['version' => $v])->save();
        });
    }

    public function setAnswered(Lesson $lesson, ChatMessage $msg, User $by, bool $answered): void
    {
        $this->change($this->chatFor($lesson), fn ($v) => $msg->forceFill([
            'answered_at' => $answered ? now() : null,
            'answered_by' => $answered ? $by->id : null,
            'version' => $v,
        ])->save());
    }

    public function delete(Lesson $lesson, ChatMessage $msg, User $by): void
    {
        $this->change($this->chatFor($lesson), function ($v, $row) use ($msg, $by) {
            $msg->forceFill(['deleted_at' => now(), 'deleted_by' => $by->id, 'version' => $v])->save();
            if ((int) $row->pinned_message_id === $msg->id) {
                $row->pinned_message_id = null;
            }
        });
    }

    public function pin(Lesson $lesson, ?ChatMessage $msg): void
    {
        $this->change($this->chatFor($lesson), function ($v, $row) use ($msg) {
            $row->pinned_message_id = $msg?->id;
        });
    }

    /** $duration: 10m | lesson | forever */
    public function mute(Lesson $lesson, User $target, string $duration, User $by): void
    {
        if (self::isTeam($target)) {
            throw new ChatRefused('team', 'Команду замьютить нельзя');
        }
        $this->change($this->chatFor($lesson), fn () => ChatMute::create([
            'user_id' => $target->id,
            'lesson_id' => $duration === 'forever' ? null : $lesson->id,
            'until' => $duration === '10m' ? now()->addMinutes(10) : null,
            'created_by' => $by->id,
        ]));
    }

    public function unmute(Lesson $lesson, User $target): void
    {
        $this->change($this->chatFor($lesson), fn () => ChatMute::activeFor($target->id, $lesson->id)
            ->update(['until' => now()]));
    }

    // ── Лента ─────────────────────────────────────────────────────────

    /**
     * Изменения после версии $since. $since = 0 — первая загрузка: последние
     * сообщения (и для команды — все неотвеченные вопросы, они могут быть
     * старше последних ста).
     */
    public function feed(Lesson $lesson, User $viewer, int $since): array
    {
        $chat = $this->chatFor($lesson);
        $segments = $this->segments($lesson->id);
        $state = $this->state($lesson, $chat, $segments);
        $out = [
            'v' => (int) $chat->version,
            'state' => $this->stateOut($state),
        ];

        $room = app(LiveChatRoom::class);
        if (self::isTeam($viewer)) {
            $out['online'] = $room->online($lesson);
        }

        if ($since === (int) $chat->version) {
            return $out;
        }

        $base = ChatMessage::where('lesson_id', $lesson->id);
        if ($since <= 0 || $since > $chat->version) {
            $limit = $this->cfg['initial_messages'];
            $msgs = (clone $base)->whereNull('deleted_at')->whereNull('thread_id')->orderByDesc('id')->limit($limit + 1)->get();
            $out['more'] = $msgs->count() > $limit;
            $msgs = $msgs->take($limit)->reverse()->values();
            if (self::isTeam($viewer)) {
                // Только во вкладку «Вопросы», не в ленту: в ленте был бы разрыв.
                $q = (clone $base)->whereNull('deleted_at')->whereNull('thread_id')->where('is_question', true)->whereNull('answered_at')
                    ->whereNotIn('id', $msgs->pluck('id'))->orderBy('id')->get();
                $out['questions'] = $this->present($q, $viewer);
            }
            $out['reset'] = true;
        } else {
            $msgs = (clone $base)->where('version', '>', $since)->where('version', '<=', $chat->version)
                ->orderBy('version')->limit(500)->get();
        }

        $out['msgs'] = $this->present($msgs, $viewer);
        $out['pinned'] = $this->pinned($chat, $viewer);
        $out['me'] = $this->me($viewer, $lesson, $state['state']);
        $out += $room->snapshot($lesson, $chat, $viewer);

        return $out;
    }

    /** Более старые сообщения — при прокрутке вверх. */
    public function older(Lesson $lesson, User $viewer, int $beforeId): array
    {
        $limit = 50;
        $msgs = ChatMessage::where('lesson_id', $lesson->id)->whereNull('deleted_at')->whereNull('thread_id')
            ->where('id', '<', $beforeId)->orderByDesc('id')->limit($limit + 1)->get();

        return [
            'more' => $msgs->count() > $limit,
            'msgs' => $this->present($msgs->take($limit)->reverse()->values(), $viewer),
        ];
    }

    /** Ветка: исходное сообщение и все ответы на него. */
    public function thread(Lesson $lesson, User $viewer, int $rootId): array
    {
        $root = ChatMessage::where('lesson_id', $lesson->id)->whereNull('thread_id')->find($rootId);
        if (! $root) {
            return ['root' => null, 'msgs' => []];
        }
        $msgs = ChatMessage::where('lesson_id', $lesson->id)->where('thread_id', $root->id)
            ->whereNull('deleted_at')->orderBy('id')->get();

        return [
            'root' => $this->present(collect([$root]), $viewer)[0],
            'msgs' => $this->present($msgs, $viewer),
        ];
    }

    /** Весь чат для записи урока, у каждого сообщения — секунда в записи. */
    public function replay(Lesson $lesson, User $viewer): array
    {
        $chat = $this->chatFor($lesson);
        $segments = $this->segments($lesson->id);
        $session = $lesson->courseSession;
        $clock = new ReplayClock(
            array_map(fn ($s) => [$s[0]->timestamp, $s[1]?->timestamp], $segments),
            (bool) $this->cfg['replay_skip_gaps'],
            $session?->date ? $session->startDateTime()->timestamp : null,
        );

        $msgs = ChatMessage::where('lesson_id', $lesson->id)->whereNull('deleted_at')->orderBy('id')->get();
        $out = $this->present($msgs, $viewer);
        foreach ($out as &$m) {
            $m['t'] = $clock->offset($m['ts']);
            // ✓ на вопросе — тоже на своём месте в записи («ответ на эфире»).
            if (! empty($m['ansTs'])) {
                $m['ansT'] = $clock->offset($m['ansTs']);
            }
        }

        return ['msgs' => $out, 'shift' => (int) $chat->shift_seconds];
    }

    private function stateOut(array $s): array
    {
        return [
            's' => $s['state'],
            'opensAt' => $s['opens_at']?->timestamp,
            'opensLabel' => $s['opens_at']?->format('H:i'),
            'breakUntil' => $s['break_until']?->timestamp,
        ];
    }

    private function pinned(LessonChat $chat, User $viewer): ?array
    {
        if (! $chat->pinned_message_id) {
            return null;
        }
        $m = ChatMessage::whereNull('deleted_at')->find($chat->pinned_message_id);

        return $m ? ($this->present(collect([$m]), $viewer)[0] ?? null) : null;
    }

    /** Сообщения → то, что видит браузер. Удалённые — только id и отметка. */
    public function present(Collection $msgs, User $viewer): array
    {
        if ($msgs->isEmpty()) {
            return [];
        }
        $live = $msgs->whereNull('deleted_at');
        $ids = $live->pluck('id')->all();

        // Сводка ветки у исходных сообщений: сколько ответов, кто ответил
        // последним (до 3 аватарок), был ли среди них кто-то из команды.
        $rootIds = $live->whereNull('thread_id')->pluck('id')->all();
        $threadRows = $rootIds
            ? ChatMessage::whereIn('thread_id', $rootIds)->whereNull('deleted_at')->orderByDesc('id')
                ->get(['id', 'thread_id', 'user_id', 'author_name', 'created_at'])
            : collect();

        $users = User::whereIn('id', $live->pluck('user_id')->merge($threadRows->pluck('user_id'))->unique())
            ->get(['id', 'name', 'first_name', 'last_name', 'avatar', 'role'])->keyBy('id');

        $tc = [];
        foreach ($threadRows as $r) {
            $t = &$tc[$r->thread_id];
            $t ??= ['c' => 0, 'who' => [], 'teach' => false, 'last' => $r->created_at->timestamp];
            $t['c']++;
            $ru = $users->get($r->user_id);
            if ($ru && self::isTeam($ru)) {
                $t['teach'] = true;
            }
            if (count($t['who']) < 3 && ! in_array($r->user_id, array_column($t['who'], 'uid'), true)) {
                $t['who'][] = ['uid' => $r->user_id, 'n' => $r->author_name, 'av' => $ru?->avatar_url];
            }
            unset($t);
        }

        $replyIds = $live->pluck('reply_to_id')->filter()->unique();
        $replies = $replyIds->isEmpty() ? collect()
            : ChatMessage::whereIn('id', $replyIds)->get(['id', 'user_id', 'author_name', 'body', 'deleted_at'])->keyBy('id');

        // Реакции команды показываются отдельно — аватаркой с эмодзи, как
        // «сердечко от автора» на YouTube, — и в общий счётчик не входят.
        $rx = [];
        $tr = [];
        if ($ids) {
            $rows = ChatMessageReaction::whereIn('message_id', $ids)->orderBy('id')
                ->get(['message_id', 'reaction_type_id', 'user_id']);
            $teamReactors = User::whereIn('id', $rows->pluck('user_id')->unique())
                ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_MENTOR])
                ->get(['id', 'name', 'first_name', 'last_name', 'avatar', 'role', 'chat_name'])->keyBy('id');
            $counts = [];
            foreach ($rows as $r) {
                $tu = $teamReactors->get($r->user_id);
                if ($tu) {
                    $tr[$r->message_id][] = [(int) $r->reaction_type_id, (int) $tu->id, $this->teamName($tu), $tu->avatar_url];

                    continue;
                }
                $c = &$counts[$r->message_id][(int) $r->reaction_type_id];
                $c ??= [0, false];
                $c[0]++;
                $c[1] = $c[1] || (int) $r->user_id === (int) $viewer->id;
                unset($c);
            }
            foreach ($counts as $mid => $byType) {
                ksort($byType);
                foreach ($byType as $tid => [$n, $mine]) {
                    $rx[$mid][] = [$tid, $n, $mine];
                }
            }
        }

        $isAdmin = $viewer->isAdmin();

        return $msgs->map(function (ChatMessage $m) use ($users, $replies, $rx, $tr, $tc, $isAdmin) {
            if ($m->deleted_at) {
                return ['id' => $m->id, 'v' => (int) $m->version, 'del' => true];
            }
            $u = $users->get($m->user_id);
            $team = $u && self::isTeam($u);
            $reply = $m->reply_to_id ? $replies->get($m->reply_to_id) : null;

            $out = [
                'id' => $m->id,
                'v' => (int) $m->version,
                'uid' => $m->user_id,
                'n' => $m->author_name,
                'b' => $m->author_badge,
                'team' => $team,
                'av' => $u?->avatar_url,
                'text' => $m->body,
                'q' => $m->is_question,
                'ans' => $m->answered_at !== null,
                'ansTs' => $m->answered_at?->timestamp,
                'ts' => $m->created_at->timestamp,
                'reply' => $reply ? [
                    'id' => $reply->id,
                    'uid' => $reply->user_id,
                    'n' => $reply->author_name,
                    'text' => $reply->deleted_at ? 'Сообщение удалено' : Str::limit($reply->body, 90),
                ] : null,
                'rx' => $rx[$m->id] ?? [],
                'tr' => $tr[$m->id] ?? [],
                'th' => $m->thread_id,
                'tc' => $m->thread_id ? null : ($tc[$m->id] ?? null),
            ];
            if ($isAdmin && $u && ! $team) {
                $out['real'] = trim($u->full_name) ?: $u->name;
            } elseif ($isAdmin && $m->user_id >= self::GUEST_BASE) {
                $out['real'] = 'Гость по ссылке, не зарегистрирован';
            }

            return $out;
        })->values()->all();
    }

    // ── Страница ──────────────────────────────────────────────────────

    /**
     * Всё, что нужно скрипту чата (public/js/live-chat.js) на странице.
     * $mode: live — чат эфира; replay — чат в записи; console — пульт в OBS.
     */
    public function clientConfig(Lesson $lesson, User $u, string $mode): array
    {
        $chat = $this->chatFor($lesson);
        $state = $this->state($lesson, $chat);
        $k = $this->feedToken($u, $lesson);
        $id = $lesson->id;

        return [
            'mode' => $mode,
            'lesson' => $id,
            'csrf' => csrf_token(),
            'me' => $this->me($u, $lesson, $state['state']),
            'askName' => ! self::isTeam($u) && ! $u->is_demo,
            'state' => $this->stateOut($state),
            'reactions' => ChatReactionType::orderBy('sort')->orderBy('id')->get()->map->toClient()->values(),
            'lottie' => asset('js/vendor/lottie_light.min.js'),
            'poll' => [$this->cfg['poll_visible_ms'], $this->cfg['poll_hidden_ms']],
            'max' => $this->cfg['message_max'],
            'nameMax' => $this->cfg['name_max'],
            'emotions' => app(LiveChatRoom::class)->emotionCatalog(),
            'emotionMax' => $this->cfg['emotion_max'],
            'emotionLevel' => $this->cfg['emotion_level'],
            'emojiNameLevel' => $this->cfg['emoji_name_level'],
            'url' => [
                'feed' => route('live-chat.feed', $id).'?k='.$k,
                'older' => route('live-chat.older', $id).'?k='.$k,
                'thread' => route('live-chat.thread', $id).'?k='.$k,
                'replay' => route('live-chat.replay', $id).'?k='.$k,
                'send' => route('live-chat.send', $id),
                'name' => route('live-chat.name', $id),
                'unpin' => route('live-chat.unpin', $id),
                // {m} / {u} подставляет скрипт
                'react' => route('live-chat.react', [$id, 0]),
                'answer' => route('live-chat.answer', [$id, 0]),
                'delete' => route('live-chat.delete', [$id, 0]),
                'pin' => route('live-chat.pin', [$id, 0]),
                'mute' => route('live-chat.mute', [$id, 0]),
                'unmute' => route('live-chat.unmute', [$id, 0]),
                'stats' => route('live-chat.stats', $id).'?k='.$k,
                'emotion' => route('live-chat.emotion', $id),
                'support' => route('live-chat.support', [$id, 0]),
                'vote' => route('live-chat.vote', $id),
                'pollStart' => route('live-chat.poll.start', $id),
                'pollClose' => route('live-chat.poll.close', $id),
                'break' => route('live-chat.break', $id),
                'quiet' => route('live-chat.quiet', $id),
            ],
        ];
    }

    // ── Ключ ленты ────────────────────────────────────────────────────

    /**
     * Лента опрашивается раз в 2 секунды — она живёт в api-группе, без сессии:
     * в web-группе каждый опрос писал бы строку sessions (SESSION_DRIVER=
     * database), это ~75 записей в секунду на эфир. Кто спрашивает — по ключу,
     * который страница урока получает при открытии (подпись app.key).
     */
    public function feedToken(User $u, Lesson $lesson): string
    {
        $exp = now()->addHours(12)->timestamp;

        return $u->id.'.'.$exp.'.'.$this->sign($u->id, $lesson->id, $exp);
    }

    public function userFromToken(?string $token, Lesson $lesson): ?User
    {
        if (! $token || ! preg_match('/^(\d+)\.(\d+)\.([a-f0-9]{64})$/', $token, $m)) {
            return null;
        }
        [, $uid, $exp, $sig] = $m;
        if ((int) $exp < now()->timestamp || ! hash_equals($this->sign((int) $uid, $lesson->id, (int) $exp), $sig)) {
            return null;
        }

        return (int) $uid >= self::GUEST_BASE ? self::guestFromId((int) $uid) : User::find((int) $uid);
    }

    private function sign(int $uid, int $lessonId, int $exp): string
    {
        return hash_hmac('sha256', "live-chat|{$uid}|{$lessonId}|{$exp}", (string) config('app.key'));
    }
}
