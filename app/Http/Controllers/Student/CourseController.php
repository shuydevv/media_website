<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Homework;
use App\Models\HomeworkTask;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class CourseController extends Controller
{
    public function show(Request $request, Course $course)
    {
        $this->authorize('view', $course);

        // Категория показывается в шапке страницы (optional($course->category)
        // ->name) — без eager-load это лишний ленивый запрос на каждый показ.
        $course->loadMissing('category');

        $tz  = config('app.timezone', 'Europe/Moscow'); // у тебя уже MSK
        $now = now();                                   // MSK
        $user = $request->user();

        // Тянем все сессии (без фильтра по start_date_time!)
        $all = $course->sessions()
            ->visible()
            ->with([
                'lesson.homework.tasks',
                // Только сабмишены ЭТОГО ученика, последний — первым (см.
                // homeworkBadgeColor(): та же логика выбора "актуального"
                // сабмишена, что и в LessonController/HomeworkController —
                // latest('id'), без разбора по статусу.
                'lesson.homework.submissions' => function ($q) use ($user) {
                    $q->where('user_id', $user->id)->orderByDesc('id');
                },
            ])
            ->get()
            ->map(function ($s) use ($tz) {
                // Нормализуем дату и время
                $day = $s->date instanceof CarbonInterface
                    ? $s->date->format('Y-m-d')
                    : (string) $s->date;

                $startTime = $s->start_time instanceof CarbonInterface
                    ? $s->start_time->format('H:i:s')
                    : ($s->start_time ?: '00:00:00');

                // START = date + start_time (строго) — как и в CourseSession
                // (см. getDisplayDateAttribute и др.), не роняем всю страницу
                // курса из-за одной кривой даты/времени: помечаем сессию как
                // непригодную (_start = null) и отфильтровываем её ниже.
                try {
                    $start = Carbon::createFromFormat('Y-m-d H:i:s', "{$day} {$startTime}", $tz);
                } catch (\Throwable $e) {
                    $s->_start = null;
                    $s->_end = null;

                    return $s;
                }

                // END = date + end_time ИЛИ start + duration (мин)
                if (!empty($s->end_time)) {
                    $endTime = $s->end_time instanceof CarbonInterface
                        ? $s->end_time->format('H:i:s')
                        : (string) $s->end_time;

                    try {
                        $end = Carbon::createFromFormat('Y-m-d H:i:s', "{$day} {$endTime}", $tz);
                    } catch (\Throwable $e) {
                        $end = $start->copy()->addMinutes((int) ($s->duration ?? 0));
                    }
                } else {
                    $end = $start->copy()->addMinutes((int)($s->duration ?? 0));
                }

                // Сохраняем вычисленное — будем использовать и для «ближайшего», и для «прошедших»
                $s->_start = $start;
                $s->_end   = $end;

                // Цвет значка домашки на картинке урока (см. homeworkBadgeColor)
                $s->_homeworkColor = $this->homeworkBadgeColor(optional($s->lesson)->homework);

                return $s;
            })
            ->filter(fn ($s) => $s->_start !== null)
            ->values();

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

    /**
     * Цвет значка домашки для картинки урока: null — домашки нет вовсе
     * (значок не показываем), 'gray' — не сдана, 'red' — просрочена (дедлайн
     * прошёл, а сдачи нет), 'blue' — сдана, ждёт ручной проверки, 'green' —
     * проверена. Логика статуса — как в HomeworkController::index() (учитывая
     * особый случай 'expired': сдано после дедлайна, но именно сдано, не
     * брошено — эта ветка там тоже разбирает наличие ручных заданий), а выбор
     * "актуального" сабмишена — как в LessonController::show() (latest('id')).
     */
    private function homeworkBadgeColor(?Homework $hw): ?string
    {
        if (!$hw) {
            return null;
        }

        $submission = $hw->submissions->first();

        if (!$submission) {
            return ($hw->due_at !== null && now()->isAfter($hw->due_at)) ? 'red' : 'gray';
        }

        if ($submission->status === 'in_progress') {
            return 'gray';
        }

        if ($submission->status === 'checked') {
            return 'green';
        }

        if ($submission->status === 'expired') {
            $hasManualTasks = $hw->tasks->contains(
                fn ($t) => in_array($t->type, HomeworkTask::MANUAL_TYPES, true)
            );

            return $hasManualTasks ? 'blue' : 'green';
        }

        return 'blue'; // 'pending' и легаси-значения — сдано, ждёт проверки
    }
}
