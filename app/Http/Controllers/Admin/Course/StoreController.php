<?php

namespace App\Http\Controllers\Admin\Course;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Course;
use App\Service\CourseScheduleService;
use App\Http\Requests\Admin\Course\StoreRequest;
use Illuminate\Support\Facades\Storage;


class StoreController extends Controller
{
    public function __construct(protected CourseScheduleService $scheduleService) {}

    public function __invoke(StoreRequest  $request)
    {
        // Валидация уже выполнена в StoreCourseRequest
        $validated = $request->validated();

        if( array_key_exists('main_image', $validated)) {
            $validated['main_image'] = Storage::disk('public')->put('/images', $validated['main_image']);
        }

        // только поля, которые действительно есть в таблице courses
        $course = Course::create([
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

        // отдельно добавляем шаблоны расписания
        foreach ($validated['schedule'] as $item) {
            $course->scheduleTemplates()->create([
                'day_of_week' => $item['day_of_week'],
                'start_time' => $item['start_time'],
                'duration_minutes' => $item['duration_minutes'],
            ]);
        }

        $course->load('scheduleTemplates');

        // генерация занятий
        $this->scheduleService->generateSessionsForCourse($course);

        // Редирект с сообщением об успехе
        return redirect()
            ->route('admin.courses.index')
            ->with('success', 'Курс успешно создан с расписанием занятий.');
    }
}

