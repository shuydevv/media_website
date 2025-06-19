<?php

namespace App\Service;

use App\Models\Course;
use App\Models\CourseSession;
use Illuminate\Support\Carbon;

class CourseScheduleService
{
    /**
     * Генерирует занятия (CourseSession) для курса на основе шаблона расписания.
     *
     * @param Course $course Курс, для которого создаётся расписание
     */
    public function generateSessionsForCourse(Course $course): void
    {
        // Получаем все шаблоны расписания для курса (например: Пн 17:00, Ср 18:00)
        $templates = $course->scheduleTemplates;

        // Устанавливаем дату начала и конца курса
        $start = Carbon::parse($course->start_date);
        $end = Carbon::parse($course->end_date);

        // Перебираем все даты от начала до конца курса
        $current = $start->copy();
        while ($current->lte($end)) {
            foreach ($templates as $template) {
                // Проверяем, совпадает ли день недели текущей даты с шаблоном (например: Mon == Mon)
                if ($current->format('D') === $template->day_of_week) {

                    // Формируем точное время начала и окончания занятия
                    $startTime = Carbon::parse($template->start_time);
                    $sessionStart = $current->copy()->setTimeFrom($startTime);
                    $sessionEnd = $sessionStart->copy()->addMinutes($template->duration_minutes);

                    // Создаём запись о конкретном занятии (CourseSession)
                    CourseSession::create([
                        'course_id'   => $course->id,
                        'date'        => $current->toDateString(),
                        'start_time'  => $sessionStart->format('H:i:s'),
                        'end_time'    => $sessionEnd->format('H:i:s'),
                        'status'      => 'active', // активное занятие
                    ]);
                }
            }

            // Переход к следующему дню
            $current->addDay();
        }
    }
}
