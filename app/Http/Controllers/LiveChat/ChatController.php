<?php

namespace App\Http\Controllers\LiveChat;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Lesson;
use App\Models\User;
use App\Service\LiveChat\ChatRefused;
use App\Service\LiveChat\LiveChatService;
use Illuminate\Http\Request;

/**
 * Действия в чате трансляции. Доступ — как к странице урока (CoursePolicy:
 * ученик с оплаченным доступом, команда — всегда). Модерация — только админ,
 * отметка вопроса ✓ — админ и куратор.
 */
class ChatController extends Controller
{
    public function __construct(private LiveChatService $chat)
    {
    }

    public function send(Request $request, Lesson $lesson)
    {
        $this->authorizeLesson($lesson);
        $data = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
            'reply_to' => ['nullable', 'integer'],
            'thread' => ['nullable', 'integer'],
            'question' => ['nullable', 'boolean'],
        ]);

        try {
            $res = $this->chat->post($lesson, $request->user(), $data['text'], $data['reply_to'] ?? null, (bool) ($data['question'] ?? false), $data['thread'] ?? null);
        } catch (ChatRefused $e) {
            return $e->toResponse();
        }

        return response()->json([
            'msg' => $this->chat->present(collect([$res['message']]), $request->user())[0],
            'slow' => $res['slow'],
        ]);
    }

    public function rename(Request $request, Lesson $lesson)
    {
        $this->authorizeLesson($lesson);
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        abort_if(LiveChatService::isTeam($request->user()), 403);

        try {
            return response()->json(['name' => $this->chat->rename($request->user(), $data['name'])]);
        } catch (ChatRefused $e) {
            return $e->toResponse();
        }
    }

    public function react(Request $request, Lesson $lesson, ChatMessage $message)
    {
        $this->authorizeLesson($lesson, $message);
        $data = $request->validate(['reaction' => ['required', 'integer']]);

        try {
            $this->chat->toggleReaction($lesson, $message, $request->user(), (int) $data['reaction']);
        } catch (ChatRefused $e) {
            return $e->toResponse();
        }

        return response()->json(['ok' => true]);
    }

    public function answer(Request $request, Lesson $lesson, ChatMessage $message)
    {
        $this->authorizeLesson($lesson, $message);
        abort_unless(LiveChatService::isTeam($request->user()), 403);
        $this->chat->setAnswered($lesson, $message, $request->user(), $request->boolean('answered', true));

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, Lesson $lesson, ChatMessage $message)
    {
        $this->authorizeLesson($lesson, $message);
        abort_unless($request->user()->isAdmin(), 403);
        $this->chat->delete($lesson, $message, $request->user());

        return response()->json(['ok' => true]);
    }

    public function pin(Request $request, Lesson $lesson, ChatMessage $message)
    {
        $this->authorizeLesson($lesson, $message);
        abort_unless($request->user()->isAdmin(), 403);
        $this->chat->pin($lesson, $message);

        return response()->json(['ok' => true]);
    }

    public function unpin(Request $request, Lesson $lesson)
    {
        $this->authorizeLesson($lesson);
        abort_unless($request->user()->isAdmin(), 403);
        $this->chat->pin($lesson, null);

        return response()->json(['ok' => true]);
    }

    public function mute(Request $request, Lesson $lesson, int $user)
    {
        $user = $this->target($user);
        $this->authorizeLesson($lesson);
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['duration' => ['required', 'in:10m,lesson,forever']]);

        try {
            $this->chat->mute($lesson, $user, $data['duration'], $request->user());
        } catch (ChatRefused $e) {
            return $e->toResponse();
        }

        return response()->json(['ok' => true]);
    }

    public function unmute(Request $request, Lesson $lesson, int $user)
    {
        $user = $this->target($user);
        $this->authorizeLesson($lesson);
        abort_unless($request->user()->isAdmin(), 403);
        $this->chat->unmute($lesson, $user);

        return response()->json(['ok' => true]);
    }

    /** Кого мьютят: пользователь или гость по ссылке. */
    private function target(int $id): User
    {
        $u = $id >= LiveChatService::GUEST_BASE ? LiveChatService::guestFromId($id) : User::find($id);
        abort_unless($u, 404);

        return $u;
    }

    private function authorizeLesson(Lesson $lesson, ?ChatMessage $message = null): void
    {
        $course = $lesson->courseSession?->course;
        abort_unless($course, 404);
        $this->authorize('view', $course);
        abort_if($message && (int) $message->lesson_id !== (int) $lesson->id, 404);
    }
}
