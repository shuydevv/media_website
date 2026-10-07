<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Учёт просмотра уроков для отчёта по ученику (/admin/users/{id}).
 * Отметки шлёт скрипт плеера Kinescope со страницы урока
 * (student/lessons/partials/watch-tracker.blade.php) — раз в ~30 секунд,
 * пока видео играет.
 */
class LessonWatchController extends Controller
{
    /**
     * Потолок секунд за одну отметку. Скрипт шлёт их каждые 30 секунд, на
     * скорости x2 это 60 секунд содержания — запас сверху на задержавшийся
     * запрос, но не больше: иначе одним запросом можно "досмотреть" урок.
     */
    private const MAX_SECONDS_PER_BEAT = 120;

    public function watch(Request $request, Lesson $lesson)
    {
        $this->authorizeLesson($lesson);

        $data = $request->validate([
            'kind' => ['required', Rule::in(LessonView::VIDEO_KINDS)],
            'seconds' => ['required', 'integer', 'min:0'],
            'position' => ['nullable', 'integer', 'min:0'],
            'duration' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($this->isImpersonating($request)) {
            return response()->json(['ok' => true]);
        }

        $seconds = min((int) $data['seconds'], self::MAX_SECONDS_PER_BEAT);
        $view = $this->touch($request, $lesson, $data['kind']);

        $update = [
            'watched_seconds' => DB::raw('watched_seconds + '.$seconds),
            'max_position_seconds' => max((int) $view->max_position_seconds, (int) ($data['position'] ?? 0)),
        ];

        if (! empty($data['duration'])) {
            $update['duration_seconds'] = (int) $data['duration'];
        }

        LessonView::whereKey($view->id)->update($update);

        return response()->json(['ok' => true]);
    }

    /**
     * Конспект — внешняя ссылка: фиксируем факт открытия и отправляем дальше.
     */
    public function notes(Request $request, Lesson $lesson)
    {
        $this->authorizeLesson($lesson);
        abort_unless($lesson->notes_link, 404);

        if (! $this->isImpersonating($request)) {
            $this->touch($request, $lesson, LessonView::KIND_NOTES);
        }

        return redirect()->away($lesson->notes_link);
    }

    private function touch(Request $request, Lesson $lesson, string $kind): LessonView
    {
        $view = LessonView::firstOrCreate(
            ['user_id' => $request->user()->id, 'lesson_id' => $lesson->id, 'kind' => $kind],
            ['first_watched_at' => now()]
        );

        $view->last_watched_at = now();
        $view->save();

        return $view;
    }

    // Тот же гейт, что и у страницы урока (LessonController::show()).
    private function authorizeLesson(Lesson $lesson): void
    {
        $course = $lesson->courseSession->course ?? null;
        abort_unless($course, 404);
        $this->authorize('view', $course);
    }

    // Админ, вошедший под учеником, не должен "смотреть уроки" за него.
    private function isImpersonating(Request $request): bool
    {
        return $request->session()->has('impersonator_id');
    }
}
