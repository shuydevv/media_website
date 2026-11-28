<?php

namespace App\Http\Controllers\LiveChat;

use App\Http\Controllers\Controller;
use App\Models\ChatEmotion;
use App\Models\ChatGuest;
use App\Models\ChatMessage;
use App\Models\Lesson;
use App\Models\LessonChat;
use App\Models\User;
use App\Service\LiveChat\ChatRefused;
use App\Service\LiveChat\ChatState;
use App\Service\LiveChat\LiveChatRoom;
use App\Service\LiveChat\LiveChatService;
use Illuminate\Http\Request;

/**
 * Гостевая ссылка на эфир урока: /live/<код>. Без регистрации, только этот
 * урок — эфир, а если урок прошёл — запись с чатом в записи. Гость пишет в
 * чат под своим именем с плашкой «Гость».
 *
 * Гость — не пользователь сайта: chat_guests + номер в сессии; в чате его id
 * = LiveChatService::GUEST_BASE + id гостя (см. LiveChatService::guestUser()).
 */
class GuestController extends Controller
{
    public function __construct(private LiveChatService $chat)
    {
    }

    public function show(Request $request, string $token)
    {
        $lesson = $this->lesson($token);
        $guest = $this->guest($request, $lesson, true);
        $user = LiveChatService::guestUser($guest);

        $state = $this->chat->state($lesson, $this->chat->chatFor($lesson))['state'];
        $replay = $state === ChatState::CLOSED && filled($lesson->recording_link);
        $hasMessages = ChatMessage::where('lesson_id', $lesson->id)->whereNull('deleted_at')->exists();

        $config = null;
        if (config('live_chat.enabled') && (! $replay || $hasMessages)) {
            $config = $this->chat->clientConfig($lesson, $user, $replay ? 'replay' : 'live');
            $config['askName'] = true;
            $base = url('/live/'.$token);
            $config['url'] = array_merge($config['url'], [
                'send' => $base.'/messages',
                'name' => $base.'/name',
                'react' => $base.'/messages/0/reactions',
                'vote' => $base.'/poll/vote',
                'support' => $base.'/emotions/0/support',
            ]);
            // Гостю — только писать, ставить реакции, голосовать и поддерживать эмоции.
            foreach (['answer', 'delete', 'pin', 'unpin', 'mute', 'unmute', 'emotion', 'pollStart', 'pollClose', 'break', 'quiet', 'stats'] as $k) {
                unset($config['url'][$k]);
            }
        }

        return view('live-chat.guest', [
            'lesson' => $lesson,
            'config' => $config,
            'replay' => $replay,
        ]);
    }

    public function send(Request $request, string $token)
    {
        [$lesson, $user] = $this->actor($request, $token);
        $data = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
            'reply_to' => ['nullable', 'integer'],
            'thread' => ['nullable', 'integer'],
            'question' => ['nullable', 'boolean'],
        ]);

        try {
            $res = $this->chat->post($lesson, $user, $data['text'], $data['reply_to'] ?? null, (bool) ($data['question'] ?? false), $data['thread'] ?? null);
        } catch (ChatRefused $e) {
            return $e->toResponse();
        }

        return response()->json(['msg' => $this->chat->present(collect([$res['message']]), $user)[0], 'slow' => $res['slow']]);
    }

    public function rename(Request $request, string $token)
    {
        [, $user] = $this->actor($request, $token);
        $name = $request->validate(['name' => ['required', 'string', 'max:100']])['name'];

        try {
            return response()->json(['name' => $this->chat->rename($user, $name)]);
        } catch (ChatRefused $e) {
            return $e->toResponse();
        }
    }

    public function react(Request $request, string $token, ChatMessage $message)
    {
        [$lesson, $user] = $this->actor($request, $token);
        abort_if((int) $message->lesson_id !== (int) $lesson->id, 404);
        $rid = $request->validate(['reaction' => ['required', 'integer']])['reaction'];

        try {
            $this->chat->toggleReaction($lesson, $message, $user, (int) $rid);
        } catch (ChatRefused $e) {
            return $e->toResponse();
        }

        return response()->json(['ok' => true]);
    }

    public function vote(Request $request, string $token, LiveChatRoom $room)
    {
        [$lesson, $user] = $this->actor($request, $token);
        $choice = $request->validate(['choice' => ['required', 'boolean']])['choice'];

        try {
            $room->vote($lesson, $user, (bool) $choice);
        } catch (ChatRefused $e) {
            return $e->toResponse();
        }

        return response()->json(['ok' => true]);
    }

    public function support(Request $request, string $token, ChatEmotion $emotion, LiveChatRoom $room)
    {
        [$lesson, $user] = $this->actor($request, $token);

        try {
            $room->supportEmotion($lesson, $user, $emotion->id);
        } catch (ChatRefused $e) {
            return $e->toResponse();
        }

        return response()->json(['ok' => true]);
    }

    public function unsupport(Request $request, string $token, ChatEmotion $emotion, LiveChatRoom $room)
    {
        [$lesson, $user] = $this->actor($request, $token);
        $room->unsupportEmotion($lesson, $user, $emotion->id);

        return response()->json(['ok' => true]);
    }

    private function lesson(string $token): Lesson
    {
        $chat = LessonChat::where('guest_token', $token)->first();
        abort_unless($chat && $chat->lesson, 404);

        return $chat->lesson;
    }

    /** Гость этой сессии; на странице — заводится при первом заходе. */
    private function guest(Request $request, Lesson $lesson, bool $create): ?ChatGuest
    {
        $key = 'live_guest_'.$lesson->id;
        $guest = ($id = $request->session()->get($key)) ? ChatGuest::where('lesson_id', $lesson->id)->find($id) : null;
        if (! $guest && $create) {
            $guest = ChatGuest::create(['lesson_id' => $lesson->id, 'ip' => $request->ip()]);
            $request->session()->put($key, $guest->id);
        }

        return $guest;
    }

    /** @return array{0: Lesson, 1: User} */
    private function actor(Request $request, string $token): array
    {
        $lesson = $this->lesson($token);
        $guest = $this->guest($request, $lesson, false);
        abort_unless($guest, 419);

        return [$lesson, LiveChatService::guestUser($guest)];
    }
}
