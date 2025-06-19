<?php

namespace App\Http\Controllers\Admin\Lesson;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lesson\StoreRequest;
use App\Models\Lesson;
use App\Models\CourseSession;
use Illuminate\Support\Facades\Storage;

class StoreController extends Controller
{
    public function __invoke(StoreRequest $request)
    {
        $validated = $request->validated();

        // Загружаем изображение, если есть
        if (isset($validated['image'])) {
            $path = $validated['image']->store('lessons', 'public');
            $validated['image'] = $path;
        }

        // Создание урока
        Lesson::create([
            'course_session_id' => $validated['course_session_id'],
            'title'             => $validated['title'],
            'meet_link'         => $validated['meet_link'] ?? null,
            'recording_link'    => $validated['recording_link'] ?? null,
            'notes_link'        => $validated['notes_link'] ?? null,
            'image'             => $validated['image'] ?? null,
            'homework_id'       => $validated['homework_id'] ?? null,
        ]);

        return redirect()->route('admin.lessons.index')
            ->with('success', 'Урок успешно создан');
    }
}
