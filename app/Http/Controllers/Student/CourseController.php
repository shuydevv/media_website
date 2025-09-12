<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class CourseController extends Controller
{
    public function show(Request $request, Course $course)
    {
        $this->authorize('view', $course);

        $tz  = config('app.timezone', 'Europe/Moscow'); // у тебя уже MSK
        $now = now();                                   // MSK

        // Тянем все сессии (без фильтра по start_date_time!)
        $all = $course->sessions()
            ->visible()
            ->with(['lesson'])
            ->get()
            ->map(function ($s) use ($tz) {
                // Нормализуем дату и время
                $day = $s->date instanceof CarbonInterface
                    ? $s->date->format('Y-m-d')
                    : (string) $s->date;

                $startTime = $s->start_time instanceof CarbonInterface
                    ? $s->start_time->format('H:i:s')
                    : ($s->start_time ?: '00:00:00');

                // START = date + start_time (строго)
                $start = Carbon::createFromFormat('Y-m-d H:i:s', "{$day} {$startTime}", $tz);

                // END = date + end_time ИЛИ start + duration (мин)
                if (!empty($s->end_time)) {
                    $endTime = $s->end_time instanceof CarbonInterface
                        ? $s->end_time->format('H:i:s')
                        : (string) $s->end_time;

                    $end = Carbon::createFromFormat('Y-m-d H:i:s', "{$day} {$endTime}", $tz);
                } else {
                    $end = $start->copy()->addMinutes((int)($s->duration ?? 0));
                }

                // Сохраняем вычисленное — будем использовать и для «ближайшего», и для «прошедших»
                $s->_start = $start;
                $s->_end   = $end;

                return $s;
            });

        // 1) Ближайшая НЕ закончившаяся (включает «идущую сейчас»)
        $nextSession = $all
            ->filter(fn ($s) => $s->_end->gt($now))
            ->sortBy(fn ($s) => $s->_start->timestamp)
            ->first();

        // 2) Прошедшие = только те, что УЖЕ закончились (end <= now) и у которых есть урок
        $pastAll = $all
            ->filter(fn ($s) => $s->_end->lte($now) && $s->lesson)
            ->sortByDesc(fn ($s) => $s->_start->timestamp)
            ->values();

        // Группируем по месяцу по корректному старту
        $pastByMonth = $pastAll->groupBy(fn ($s) => $s->_start->locale('ru')->isoFormat('MMMM'));

        // Сколько прошло, но скрыто (без урока)
        $pastHiddenCount = $all
            ->filter(fn ($s) => $s->_end->lte($now) && !$s->lesson)
            ->count();

        return view('student.courses.show', [
            'course'          => $course,
            'nextSession'     => $nextSession,
            'pastByMonth'     => $pastByMonth,
            'pastHiddenCount' => $pastHiddenCount,
        ]);
    }
}
