<?php

namespace App\Http\Controllers\Admin\Lesson;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lesson\UpdateRequest;
use App\Models\Lesson;
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
            $validated['image'] = $validated['image']->store('lessons', 'public');
        }

        // Обновляем остальные поля
        $lesson->update([
            // 'course_session_id' => $validated['course_session_id'],
            'title'             => $validated['title'],
            'description' => $validated['description'] ?? null,
            'meet_link'         => $validated['meet_link'] ?? null,
            'recording_link'    => $validated['recording_link'] ?? null,
            'notes_link'        => $validated['notes_link'] ?? null,
            'image'             => $validated['image'] ?? $lesson->image,
            // 'homework_id'       => $validated['homework_id'] ?? null,
        ]);

        return redirect()->route('admin.lessons.index')
            ->with('success', 'Урок успешно обновлён');
    }
}
