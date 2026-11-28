<?php

namespace App\Http\Controllers\LiveChat;

use App\Http\Controllers\Controller;
use App\Models\ChatEmotion;
use App\Models\Lesson;
use App\Service\LiveChat\ChatRefused;
use App\Service\LiveChat\LiveChatRoom;
use Illuminate\Http\Request;

/**
 * Пульт эфира и то, что висит над лентой: эмоции, «Пон / Не пон», перерыв,
 * тишина. Управление (опрос, перерыв, тишина) — только админ: на эфире это
 * преподаватель. Доступ к уроку — как к его странице (CoursePolicy).
 */
class RoomController extends Controller
{
    public function __construct(private LiveChatRoom $room)
    {
    }

    public function emotion(Request $request, Lesson $lesson)
    {
        $this->authorizeLesson($lesson);
        $code = $request->validate(['code' => ['required', 'string', 'max:24']])['code'];

        return $this->run(fn () => $this->room->proposeEmotion($lesson, $request->user(), $code));
    }

    public function support(Request $request, Lesson $lesson, ChatEmotion $emotion)
    {
        $this->authorizeLesson($lesson);

        return $this->run(fn () => $this->room->supportEmotion($lesson, $request->user(), $emotion->id));
    }

    public function unsupport(Request $request, Lesson $lesson, ChatEmotion $emotion)
    {
        $this->authorizeLesson($lesson);

        return $this->run(fn () => $this->room->unsupportEmotion($lesson, $request->user(), $emotion->id));
    }

    public function withdraw(Request $request, Lesson $lesson, ChatEmotion $emotion)
    {
        $this->authorizeLesson($lesson);

        return $this->run(fn () => $this->room->withdrawEmotion($lesson, $request->user(), $emotion->id));
    }

    public function vote(Request $request, Lesson $lesson)
    {
        $this->authorizeLesson($lesson);
        $choice = $request->validate(['choice' => ['required', 'boolean']])['choice'];

        return $this->run(fn () => $this->room->vote($lesson, $request->user(), (bool) $choice));
    }

    // Корм за верный ответ — админ и куратор (LiveChatRewards сам проверяет команду).
    public function reward(Request $request, Lesson $lesson, \App\Models\ChatMessage $message, \App\Service\LiveChat\LiveChatRewards $rewards)
    {
        $this->authorizeLesson($lesson);

        return $this->run(fn () => $rewards->give($lesson, $message, $request->user()));
    }

    public function unreward(Request $request, Lesson $lesson, \App\Models\ChatMessage $message, \App\Service\LiveChat\LiveChatRewards $rewards)
    {
        $this->authorizeLesson($lesson);

        return $this->run(fn () => $rewards->take($lesson, $message, $request->user()));
    }

    // Статус ученика (админ): POST — поставить/поменять, DELETE — убрать.
    public function setStatus(Request $request, Lesson $lesson, int $user, \App\Service\LiveChat\LiveChatStatuses $statuses)
    {
        $this->authorizeAdmin($request, $lesson);
        $d = $request->validate(['text' => ['required', 'string', 'max:24'], 'color' => ['nullable', 'string'], 'forever' => ['boolean']]);

        return $this->run(fn () => $statuses->set($lesson, $request->user(), $user, $d['text'], $d['color'] ?? 'blue', (bool) ($d['forever'] ?? false)));
    }

    public function removeStatus(Request $request, Lesson $lesson, int $user, \App\Service\LiveChat\LiveChatStatuses $statuses)
    {
        $this->authorizeAdmin($request, $lesson);

        return $this->run(fn () => $statuses->remove($lesson, $request->user(), $user));
    }

    // Итоги урока: GET — предпросмотр всех блоков, POST — отправить в чат.
    public function summary(Request $request, Lesson $lesson, \App\Service\LiveChat\LiveChatSummary $summary)
    {
        $this->authorizeAdmin($request, $lesson);

        return response()->json(['blocks' => $summary->build($lesson)]);
    }

    public function sendSummary(Request $request, Lesson $lesson, \App\Service\LiveChat\LiveChatSummary $summary)
    {
        $this->authorizeAdmin($request, $lesson);
        $data = $request->validate([
            'blocks' => ['array'],
            'blocks.*' => ['string'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $summary->send($lesson, $request->user(), $data['blocks'] ?? [], $data['note'] ?? null);

        return response()->json(['ok' => true]);
    }

    public function startPoll(Request $request, Lesson $lesson)
    {
        $this->authorizeAdmin($request, $lesson);

        return $this->run(fn () => $this->room->startPoll($lesson, $request->user()));
    }

    public function closePoll(Request $request, Lesson $lesson)
    {
        $this->authorizeAdmin($request, $lesson);

        return $this->run(fn () => $this->room->closePoll($lesson));
    }

    public function setBreak(Request $request, Lesson $lesson)
    {
        $this->authorizeAdmin($request, $lesson);
        $min = $request->validate(['minutes' => ['required', 'integer', 'between:0,60']])['minutes'];

        return $this->run(fn () => $this->room->setBreak($lesson, (int) $min));
    }

    public function setQuiet(Request $request, Lesson $lesson)
    {
        $this->authorizeAdmin($request, $lesson);
        $min = $request->validate(['minutes' => ['required', 'integer', 'between:0,60']])['minutes'];

        return $this->run(fn () => $this->room->setQuiet($lesson, (int) $min));
    }

    private function run(callable $fn)
    {
        try {
            $fn();
        } catch (ChatRefused $e) {
            return $e->toResponse();
        }

        return response()->json(['ok' => true]);
    }

    private function authorizeLesson(Lesson $lesson): void
    {
        $course = $lesson->courseSession?->course;
        abort_unless($course, 404);
        $this->authorize('view', $course);
    }

    private function authorizeAdmin(Request $request, Lesson $lesson): void
    {
        $this->authorizeLesson($lesson);
        abort_unless($request->user()->isAdmin(), 403);
    }
}
