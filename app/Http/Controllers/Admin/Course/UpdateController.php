<?php

namespace App\Http\Controllers\Admin\Course;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Course\UpdateRequest;
use App\Models\Course;
use App\Service\CourseScheduleService;
use Illuminate\Support\Facades\Storage;

class UpdateController extends Controller
{
    public function __construct(protected CourseScheduleService $scheduleService) {}

    public function __invoke(UpdateRequest $request, Course $course)
    {
        $validated = $request->validated();

        // --- Удаление старого изображения, если загружено новое ---
        if (isset($validated['main_image'])) {
            if ($course->main_image && Storage::disk('public')->exists($course->main_image)) {
                Storage::disk('public')->delete($course->main_image);
            }
            $validated['main_image'] = Storage::disk('public')->put('/images', $validated['main_image']);
        } else {
            $validated['main_image'] = $course->main_image;
        }

        // --- Сравнение старого и нового расписания ---
        $old = $course->scheduleTemplates->map(fn($s) => [
            'day_of_week' => $s->day_of_week,
            'start_time' => $s->start_time,
            'duration_minutes' => $s->duration_minutes,
        ])->sort()->values();

        $new = collect($validated['schedule'])->map(fn($s) => [
            'day_of_week' => $s['day_of_week'],
            'start_time' => $s['start_time'],
            'duration_minutes' => (int) $s['duration_minutes'],
        ])->sort()->values();

        $scheduleChanged = $old != $new;

        // --- Сравнение дат начала и окончания курса ---
        $dateChanged =
            $course->start_date !== $validated['start_date'] ||
            $course->end_date !== $validated['end_date'];

        // --- Обновление самого курса ---
        $course->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'old_price' => $validated['old_price'] ?? null,
            'content' => $validated['content'] ?? null,
            'path' => $validated['path'] ?? null,
            'html_title' => $validated['html_title'] ?? null,
            'html_description' => $validated['html_description'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'main_image' => $validated['main_image'],
        ]);

        // --- Если расписание или даты изменились — пересоздаём шаблоны и занятия ---
        if ($scheduleChanged || $dateChanged) {
            $course->scheduleTemplates()->delete();
            $course->sessions()->delete();

            foreach ($validated['schedule'] as $item) {
                $course->scheduleTemplates()->create([
                    'day_of_week' => $item['day_of_week'],
                    'start_time' => $item['start_time'],
                    'duration_minutes' => $item['duration_minutes'],
                ]);
            }

            $this->scheduleService->generateSessionsForCourse($course);
        }

        return redirect()
            ->route('admin.courses.index')
            ->with('success', 'Курс успешно обновлён');
    }
}
