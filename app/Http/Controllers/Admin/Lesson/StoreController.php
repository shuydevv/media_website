<?php

namespace App\Http\Controllers\Admin\Lesson;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lesson\StoreRequest;
use App\Models\Lesson;
use App\Models\CourseSession;
use App\Notifications\LessonRecordingAvailableNotification;
use App\Service\BillingService;
use App\Service\ImageCompressor;
use Illuminate\Support\Facades\Storage;

class StoreController extends Controller
{
    public function __invoke(StoreRequest $request)
    {

        $validated = $request->validated();

        // Загружаем изображение, если есть
        if (isset($validated['image'])) {
            $validated['image'] = ImageCompressor::forContent()->storeAs($validated['image'], 'lessons');
        }

        // Создание урока
        $lesson = Lesson::create([
            'course_session_id' => $validated['course_session_id'],
            'title'             => $validated['title'],
            'description' => $validated['description'] ?? null,
            'lesson_type' => $validated['lesson_type'] ?? null,
            'meet_link'         => $validated['meet_link'] ?? null,
            'recording_link'    => $validated['recording_link'] ?? null,
            'short_class' => $validated['short_class'] ?? null,
            'notes_link'        => $validated['notes_link'] ?? null,
            'image'             => $validated['image'] ?? null,
            // 'homework_id'       => $validated['homework_id'] ?? null,
        ]);

        // Редкий случай — ссылку на запись указали сразу при создании урока.
        if (filled($validated['recording_link'] ?? null)) {
            $this->notifyRecordingAvailable($lesson);
        }

        return redirect()->route('admin.lessons.index')
            ->with('success', 'Урок успешно создан');
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
