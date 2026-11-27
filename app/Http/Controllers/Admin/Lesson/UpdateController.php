<?php

namespace App\Http\Controllers\Admin\Lesson;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lesson\UpdateRequest;
use App\Models\Lesson;
use App\Models\LessonChat;
use App\Notifications\LessonRecordingAvailableNotification;
use App\Service\BillingService;
use App\Service\ImageCompressor;
use Illuminate\Support\Facades\Storage;

class UpdateController extends Controller
{
    public function __invoke(UpdateRequest $request, Lesson $lesson)
    {
        $validated = $request->validated();

        // Заменяем изображение, если загружено новое
        if (isset($validated['image'])) {
            // Удаляем старое изображение, если оно было
            if ($lesson->image && Storage::disk('public')->exists($lesson->image)) {
                Storage::disk('public')->delete($lesson->image);
            }

            // Сохраняем новое изображение
            $validated['image'] = ImageCompressor::forContent()->storeAs($validated['image'], 'lessons');
        }

        $recordingWasEmpty = blank($lesson->recording_link);

        // Обновляем остальные поля
        $lesson->update([
            // 'course_session_id' => $validated['course_session_id'],
            'title'             => $validated['title'],
            'description' => $validated['description'] ?? null,
            'lesson_type' => $validated['lesson_type'] ?? null,
            'meet_link'         => $validated['meet_link'] ?? null,
            'recording_link'    => $validated['recording_link'] ?? null,
            'short_class' => $validated['short_class'] ?? null,
            'notes_link'        => $validated['notes_link'] ?? null,
            'image'             => $validated['image'] ?? $lesson->image,
            // 'homework_id'       => $validated['homework_id'] ?? null,
        ]);

        // Сдвиг чата в записи — на случай, если запись разойдётся с чатом
        // (см. LiveChatService::replay()). Обычно 0.
        if (array_key_exists('chat_shift_seconds', $validated)) {
            LessonChat::updateOrCreate(['lesson_id' => $lesson->id], ['shift_seconds' => (int) ($validated['chat_shift_seconds'] ?? 0)]);
        }

        // Уведомляем учеников только на переходе "ссылки не было -> появилась",
        // а не на каждое сохранение формы.
        if ($recordingWasEmpty && filled($validated['recording_link'] ?? null)) {
            $this->notifyRecordingAvailable($lesson);
        }

        return redirect()->route('admin.lessons.index')
            ->with('success', 'Урок успешно обновлён');
    }

    private function notifyRecordingAvailable(Lesson $lesson): void
    {
        $course = $lesson->courseSession?->course;
        if (!$course) {
            return;
        }

        foreach (app(BillingService::class)->activeStudentsWithAccess($course) as $student) {
            $student->notify(new LessonRecordingAvailableNotification($lesson));
        }
    }
}
