<?php

namespace App\Http\Controllers\LiveChat;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Service\LiveChat\ChatState;
use App\Service\LiveChat\LiveChatService;
use Illuminate\Http\Request;

/**
 * Пульт эфира — чат и вкладка «Вопросы» без видео и шапки сайта, чтобы
 * держать его в OBS («Вид → Доки → Пользовательские браузерные доки»).
 * /live-chat/console — постоянный адрес: сам выбирает урок, который идёт
 * сейчас или ближайший, чтобы не менять адрес в OBS каждый урок.
 */
class ConsoleController extends Controller
{
    public function __construct(private LiveChatService $chat)
    {
    }

    public function current(Request $request)
    {
        abort_unless(LiveChatService::isTeam($request->user()), 403);
        $lesson = $this->pickLesson();

        return $lesson ? $this->render($request, $lesson, true)
            : response()->view('live-chat.console', ['lesson' => null, 'config' => null, 'auto' => true]);
    }

    public function show(Request $request, Lesson $lesson)
    {
        abort_unless(LiveChatService::isTeam($request->user()), 403);

        return $this->render($request, $lesson, false);
    }

    public function signature(Request $request)
    {
        abort_unless(LiveChatService::isTeam($request->user()), 403);
        $data = $request->validate([
            'chat_name' => ['required', 'string', 'max:64'],
            'chat_badge' => ['required', 'string', 'max:32'],
        ]);
        $request->user()->forceFill([
            'chat_name' => trim($data['chat_name']),
            'chat_badge' => trim($data['chat_badge']),
        ])->save();

        return back();
    }

    private function render(Request $request, Lesson $lesson, bool $auto)
    {
        return view('live-chat.console', [
            'lesson' => $lesson,
            'auto' => $auto,
            'config' => $this->chat->clientConfig($lesson, $request->user(), 'console'),
        ]);
    }

    /** Идёт сейчас → открыт по расписанию → ближайший будущий → последний прошедший. */
    private function pickLesson(): ?Lesson
    {
        $lessons = Lesson::query()
            ->whereNotNull('meet_link')->where('meet_link', '!=', '')
            ->join('course_sessions', 'course_sessions.id', '=', 'lessons.course_session_id')
            ->whereBetween('course_sessions.date', [now()->subDay()->toDateString(), now()->addDays(7)->toDateString()])
            ->orderBy('course_sessions.date')->orderBy('course_sessions.start_time')
            ->select('lessons.*')->with('courseSession')->get();

        $upcoming = null;
        $past = null;
        foreach ($lessons as $lesson) {
            $state = $this->chat->state($lesson, $this->chat->chatFor($lesson))['state'];
            if (ChatState::canWrite($state)) {
                return $lesson;
            }
            if ($state === ChatState::WAITING) {
                $upcoming ??= $lesson;
            } else {
                $past = $lesson;
            }
        }

        return $upcoming ?? $past;
    }
}
