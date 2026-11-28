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

    public function vote(Request $request, Lesson $lesson)
    {
        $this->authorizeLesson($lesson);
        $choice = $request->validate(['choice' => ['required', 'boolean']])['choice'];

        return $this->run(fn () => $this->room->vote($lesson, $request->user(), (bool) $choice));
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
