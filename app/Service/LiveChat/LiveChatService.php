<?php

namespace App\Service\LiveChat;

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

    /** Имя ученика по умолчанию: последнее использованное, иначе имя из профиля. */
    public function studentName(User $u): string
    {
        $name = $u->chat_name ?: ($u->first_name ?: $u->name);

        return Str::limit(trim((string) $name) ?: 'Ученик', $this->cfg['name_max'], '');
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

        return [
            'id' => $u->id,
            'name' => $team ? $this->teamName($u) : $this->studentName($u),
            'badge' => $team ? $this->teamBadge($u) : null,
            'team' => $team,
            'admin' => $u->isAdmin(),
            'demo' => (bool) $u->is_demo,
            'muted' => $mute ? ['until' => $mute->until?->timestamp, 'lesson' => $mute->lesson_id !== null] : null,
            'write' => ChatState::canWrite($state) && ! $mute && ! $u->is_demo,
        ];
    }

    // ── Изменения ─────────────────────────────────────────────────────

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
    public function post(Lesson $lesson, User $u, string $text, ?int $replyTo, bool $question): array
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

        $reply = $replyTo
            ? ChatMessage::where('lesson_id', $lesson->id)->whereNull('deleted_at')->find($replyTo)
            : null;

        $msg = $this->change($chat, fn ($v) => ChatMessage::create([
            'lesson_id' => $lesson->id,
            'user_id' => $u->id,
            'author_name' => $team ? $this->teamName($u) : $this->studentName($u),
            'author_badge' => $team ? $this->teamBadge($u) : null,
            'body' => $text,
            'reply_to_id' => $reply?->id,
            'is_question' => $question && ! $team,
            'version' => $v,
        ]));

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
        $name = Str::limit(trim(preg_replace('/\s+/u', ' ', $name)), $this->cfg['name_max'], '');
        if ($name === '') {
            throw new ChatRefused('empty', 'Введите имя');
        }
        $u->forceFill(['chat_name' => $name])->save();

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

        if ($since === (int) $chat->version) {
            return $out;
        }

        $base = ChatMessage::where('lesson_id', $lesson->id);
        if ($since <= 0 || $since > $chat->version) {
            $limit = $this->cfg['initial_messages'];
            $msgs = (clone $base)->whereNull('deleted_at')->orderByDesc('id')->limit($limit + 1)->get();
            $out['more'] = $msgs->count() > $limit;
            $msgs = $msgs->take($limit)->reverse()->values();
            if (self::isTeam($viewer)) {
                // Только во вкладку «Вопросы», не в ленту: в ленте был бы разрыв.
                $q = (clone $base)->whereNull('deleted_at')->where('is_question', true)->whereNull('answered_at')
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

        return $out;
    }

    /** Более старые сообщения — при прокрутке вверх. */
    public function older(Lesson $lesson, User $viewer, int $beforeId): array
    {
        $limit = 50;
        $msgs = ChatMessage::where('lesson_id', $lesson->id)->whereNull('deleted_at')
            ->where('id', '<', $beforeId)->orderByDesc('id')->limit($limit + 1)->get();

        return [
            'more' => $msgs->count() > $limit,
            'msgs' => $this->present($msgs->take($limit)->reverse()->values(), $viewer),
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

        $users = User::whereIn('id', $live->pluck('user_id')->unique())
            ->get(['id', 'name', 'first_name', 'last_name', 'avatar', 'role'])->keyBy('id');

        $replyIds = $live->pluck('reply_to_id')->filter()->unique();
        $replies = $replyIds->isEmpty() ? collect()
            : ChatMessage::whereIn('id', $replyIds)->get(['id', 'user_id', 'author_name', 'body', 'deleted_at'])->keyBy('id');

        $rx = [];
        if ($ids) {
            $rows = ChatMessageReaction::whereIn('message_id', $ids)
                ->selectRaw('message_id, reaction_type_id, count(*) as c, sum(case when user_id = ? then 1 else 0 end) as mine', [$viewer->id])
                ->groupBy('message_id', 'reaction_type_id')->orderBy('reaction_type_id')->get();
            foreach ($rows as $r) {
                $rx[$r->message_id][] = [(int) $r->reaction_type_id, (int) $r->c, (int) $r->mine > 0];
            }
        }

        $isAdmin = $viewer->isAdmin();

        return $msgs->map(function (ChatMessage $m) use ($users, $replies, $rx, $isAdmin) {
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
            ];
            if ($isAdmin && $u && ! $team) {
                $out['real'] = trim($u->full_name) ?: $u->name;
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
            'url' => [
                'feed' => route('live-chat.feed', $id).'?k='.$k,
                'older' => route('live-chat.older', $id).'?k='.$k,
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

        return User::find((int) $uid);
    }

    private function sign(int $uid, int $lessonId, int $exp): string
    {
        return hash_hmac('sha256', "live-chat|{$uid}|{$lessonId}|{$exp}", (string) config('app.key'));
    }
}
