<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
public function show(\Illuminate\Http\Request $request, \App\Models\Course $course)
{
    $this->authorize('view', $course);

    $now = now();

    // Берём все сессии курса и считаем дату через аксессор start_date_time
    $all = $course->sessions()
        ->visible()
        ->with(['lesson'])
        ->get()
        ->filter(fn ($s) => $s->start_date_time); // только те, у которых дата задана

    // Ближайшая будущая сессия (показываем даже без урока)
    $nextSession = $all
        ->sortBy(fn ($s) => $s->start_date_time->timestamp)
        ->first(fn ($s) => $s->start_date_time->gte($now));

    // Прошедшие сессии: только с привязанным уроком
    $pastAll = $all
        ->filter(fn ($s) => $s->start_date_time->lt($now) && $s->lesson)
        ->sortByDesc(fn ($s) => $s->start_date_time->timestamp)
        ->values();

    // Группировка по месяцу (на русском). Пример ключа: "сентябрь".
    $pastByMonth = $pastAll->groupBy(function ($s) {
        return $s->start_date_time->locale('ru')->isoFormat('MMMM');
    });

    // Сколько прошедших скрыто (без урока) — для подсказки
    $pastHiddenCount = $all
        ->filter(fn ($s) => $s->start_date_time->lt($now) && !$s->lesson)
        ->count();

    return view('student.courses.show', [
        'course'          => $course,
        'nextSession'     => $nextSession,
        'pastByMonth'     => $pastByMonth,
        'pastHiddenCount' => $pastHiddenCount,
    ]);
}





}
