<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Homework;
use App\Models\Submission;
use App\Service\BillingService;


class DashboardController extends Controller
{
    public function __invoke(BillingService $billing)
    {
        $user = auth()->user();

        // ────────────────────────────────────────────────────────────────────
        // Курсы пользователя (обходя $user->courses()):
        // через сторону Course -> students (belongsToMany)
        // ────────────────────────────────────────────────────────────────────
        // ⚠️ Предполагается, что pivot-таблица называется course_user,
        // а в ней есть поля status и expires_at (как в твоём прежнем коде).
        $courses = Course::query()
            ->whereHas('students', function ($q) use ($user) {
                $q->where('users.id', $user->id)
                  ->where('course_user.status', 'active')
                  ->where(function ($w) {
                      $w->whereNull('course_user.expires_at')
                        ->orWhere('course_user.expires_at', '>=', now());
                  });
            })
            ->with('category')
            ->orderBy('title')
            ->get();

        // ────────────────────────────────────────────────────────────────────
        // Окно расписания: сегодня + 6 дней
        // ────────────────────────────────────────────────────────────────────
        // Опорная дата (сначала пытаемся взять request('date'), иначе сдвиг d, иначе сегодня)
        $pivot = null;
        if ($dateStr = request('date')) {
            try { $pivot = \Illuminate\Support\Carbon::parse($dateStr)->startOfDay(); } catch (\Throwable $e) {}
        }
        if (!$pivot && request()->has('d')) {
            $pivot = \Illuminate\Support\Carbon::today()->addDays((int) request('d'));
        }
        if (!$pivot) {
            $pivot = \Illuminate\Support\Carbon::today();
        }

        // Окно: -14 .. +14 дней (включительно) вокруг опорной даты
        $from = $pivot->copy()->subDays(14)->startOfDay();
        $to   = $pivot->copy()->addDays(14)->endOfDay();

        $courseIds = $courses->pluck('id')->all();

        // Курс может остаться в списке «Мои курсы» (мы его не прячем — он
        // оплачен, просто сейчас просрочен), но его уроки, домашки, очередь
        // и «ближайшее событие» ниже не должны подмешиваться на дашборд, если
        // доступ приостановлен (см. BillingService::hasAccess()) — иначе
        // блокировка де-факто работает только на странице самого курса, а с
        // дашборда всё видно и доступно так, будто ничего не просрочено.
        $blockedCourseIds = $courses
            ->reject(fn ($course) => $billing->hasAccess($user, $course))
            ->pluck('id')
            ->all();

        $courseIds = array_values(array_diff($courseIds, $blockedCourseIds));

        // Уроки в окне дат по курсам пользователя
        // $lessons = Lesson::query()
        //     ->whereHas('courseSession', fn($q) => $q->whereIn('course_id', $courseIds))
        //     ->whereNotNull('display_date')
        //     ->whereBetween('display_date', [$from->toDateString(), $to->toDateString()])
        //     ->with(['homework', 'courseSession.course'])
        //     ->get();

        // Уроки в окне дат по курсам пользователя — фильтруем по датам СЕССИЙ
        $lessons = Lesson::query()
            ->whereHas('courseSession', function ($q) use ($courseIds, $from, $to) {
                $q->whereIn('course_id', $courseIds)
                ->where(function ($qq) use ($from, $to) {
                    if (Schema::hasColumn('course_sessions', 'start_at')) {
                        $qq->orWhereBetween('start_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
                    }
                    if (Schema::hasColumn('course_sessions', 'start_date')) {
                        $qq->orWhereBetween('start_date', [$from->toDateString(), $to->toDateString()]);
                    }
                    if (Schema::hasColumn('course_sessions', 'date')) {
                        $qq->orWhereBetween('date', [$from->toDateString(), $to->toDateString()]);
                    }
                });
            })
            ->with(['homework', 'courseSession.course'])
            ->get();


        // Домашки с дедлайном в окне дат
        // $homeworks = Homework::query()
        //     ->whereIn('lesson_id', $lessons->pluck('id')->all())
        //     ->whereNotNull('due_at')
        //     ->whereBetween('due_at', [$from, $to])
        //     ->with(['lesson.course'])
        //     ->get();
        // Домашки с дедлайном в окне дат (по курсам пользователя)
$homeworks = Homework::query()
    ->whereIn('course_id', $courseIds)
    ->whereNotNull('due_at')
    ->whereBetween('due_at', [$from, $to])
    ->with(['lesson.courseSession.course'])
    ->get()
    // Пока урок ещё не наступил, ученик не должен знать о домашке к нему —
    // ни в расписании, ни где-либо ещё (см. Homework::isLessonUpcoming()).
    ->reject(fn (Homework $hw) => $hw->isLessonUpcoming())
    ->values();

        // Сабмишены пользователя по этим домашкам — без $user->submissions()
        $userSubmissions = Submission::query()
            ->where('user_id', $user->id)
            ->whereIn('homework_id', $homeworks->pluck('id')->all())
            ->get()
            ->groupBy('homework_id');

        // Каркас дней (-14 .. +14)
        $daysMap = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            $daysMap[$key] = [
                // ↓ эти три поля ждёт Blade
                'day'       => $d->locale('ru')->isoFormat('dd'),     // Пн, Вт …
                'date'      => $d->locale('ru')->isoFormat('D MMMM'), // 17 июня
                'highlight' => $d->isSameDay($pivot),                 // подсветка опорного дня

                // то, что уже было/может пригодиться
                'is_today'  => $d->isToday(),
                'items'     => [],
                // при желании можешь оставить «сырой» объект даты отдельно:
                // 'date_at'   => $d->copy(),
            ];
        }

// Вебинары (уроки)
foreach ($lessons as $lesson) {
    $session = $lesson->courseSession;
    if (!$session) continue;

    // Ключ дня
    $dateStr = $session->start_date ?? $session->date ?? null;
    if (!$dateStr && !empty($session->start_at)) {
        try { $dateStr = Carbon::parse($session->start_at)->toDateString(); } catch (\Throwable $e) { $dateStr = null; }
    }
    if (!$dateStr) continue;
    $dKey = Carbon::parse($dateStr)->toDateString();
    if (!isset($daysMap[$dKey])) continue;

    // Время
    $time = $session->start_time ?? $session->time ?? null;
    if (!$time && !empty($session->start_at)) {
        try { $time = Carbon::parse($session->start_at)->format('H:i'); } catch (\Throwable $e) { $time = null; }
    }

    // Тип и цвет
    $type  = $lesson->lesson_type === 'practice' ? 'Практика' : 'Теория';
    $color = $lesson->lesson_type === 'practice' ? 'purple'   : 'blue';

    // Название курса без " , …"
    $courseTitle = optional($session?->course)->title ?? 'Курс';
    $subject     = preg_replace('/,.*$/u', '', $courseTitle);

    $daysMap[$dKey]['items'][] = [
        'type'    => $type,
        'subject' => $subject,
        'title'   => $lesson->title ?? 'Урок',
        'time'    => $time ? mb_substr($time, 0, 5) : '—:—',
        'color'   => $color,
        'status'  => null,
        'lesson'  => $lesson,
    ];
}


        // Домашки/Пробники
        foreach ($homeworks as $hw) {
            $due = $hw->due_at ? Carbon::parse($hw->due_at) : null;
            if (!$due) continue;

            $dKey = $due->toDateString();
            if (!isset($daysMap[$dKey])) continue;

            $subs = $userSubmissions->get($hw->id) ?? collect();
            $isCompleted = $subs->isNotEmpty();
            $isOverdue   = !$isCompleted && $due->isPast();

            $subjectCourseTitle = optional($hw->lesson?->courseSession?->course)->title ?? 'Курс';
            $subjectClean       = preg_replace('/,.*$/u', '', $subjectCourseTitle);

            $color = $isOverdue ? 'red' : ($hw->type === 'mock' ? 'orange' : 'yellow');

            $daysMap[$dKey]['items'][] = [
                'type'     => $hw->type === 'mock' ? 'Пробник' : 'Домашка',
                'subject'  => $subjectClean,
                'title'    => $hw->title ?? 'Задание',
                'time'     => 'до ' . $due->format('H:i'),
                'color'    => $color,
                'status'   => $isCompleted ? 'completed' : ($isOverdue ? 'overdue' : null),
                'homework' => $hw,
            ];
        }

        // Сортируем карточки каждого дня по времени (уроки — по старту сессии, домашки — по дедлайну)
foreach ($daysMap as $dateKey => &$dayRow) {
    $dayRow['items'] = collect($dayRow['items'] ?? [])->sortBy(function ($it) {
        // Домашка: сортируем по due_at
        if (!empty($it['homework']) && $it['homework'] instanceof \App\Models\Homework) {
            $due = $it['homework']->due_at ? \Illuminate\Support\Carbon::parse($it['homework']->due_at) : null;
            return $due ? $due->timestamp : PHP_INT_MAX - 1000;
        }

        // Урок: сортируем по времени начала course_session
        if (!empty($it['lesson']) && $it['lesson'] instanceof \App\Models\Lesson) {
            $lesson  = $it['lesson'];
            $session = $lesson->courseSession ?? null;
            $dt = null;

            if ($session) {
                // полное datetime
                if (!empty($session->start_at)) {
                    try { $dt = \Illuminate\Support\Carbon::parse($session->start_at); } catch (\Throwable $e) {}
                }
                // дата + время раздельно
                if (!$dt) {
                    $dateStr = $session->start_date ?? $session->date ?? null;
                    $timeStr = $session->start_time ?? $session->time ?? null;
                    if ($dateStr && $timeStr) {
                        try { $dt = \Illuminate\Support\Carbon::parse($dateStr.' '.$timeStr); } catch (\Throwable $e) {}
                    } elseif ($dateStr) {
                        try { $dt = \Illuminate\Support\Carbon::parse($dateStr.' 00:00'); } catch (\Throwable $e) {}
                    }
                }
            }

            // Fallback: display_date/display_time (если где-то ещё используются)
            if (!$dt) {
                $dateStr = $lesson->display_date ?? null;
                $timeStr = $lesson->display_time ?? null;
                if ($dateStr && $timeStr) {
                    try { $dt = \Illuminate\Support\Carbon::parse($dateStr.' '.$timeStr); } catch (\Throwable $e) {}
                } elseif ($dateStr) {
                    try { $dt = \Illuminate\Support\Carbon::parse($dateStr.' 00:00'); } catch (\Throwable $e) {}
                }
            }

            return $dt ? $dt->timestamp : PHP_INT_MAX;
        }

        // На всякий случай — в конец
        return PHP_INT_MAX;
    })->values()->all();
}

        $days = array_values($daysMap);

        // ────────────────────────────────────────────────────────────────────
        // Карточка «Ближайшее событие»: первый ещё не выполненный урок/домашка
        // начиная с сегодняшнего дня (уже отсортировано выше по времени внутри дня).
        // ────────────────────────────────────────────────────────────────────
        $nextItem = null;
        $todayIndex = null;
        foreach ($days as $i => $d) {
            if (!empty($d['is_today'])) { $todayIndex = $i; break; }
        }
        if ($todayIndex !== null) {
            for ($i = $todayIndex; $i < count($days); $i++) {
                foreach ($days[$i]['items'] as $it) {
                    if (($it['status'] ?? null) !== 'completed') {
                        $nextItem = $it + ['day' => $days[$i]['day'], 'date' => $days[$i]['date']];
                        break 2;
                    }
                }
            }
        }

        // ────────────────────────────────────────────────────────────────────
        // Карточка «Ближайшие домашки»: очередь домашек, которые ещё не сданы
        // (не считая уже завершённых попыток), отсортирована по дедлайну.
        // Отдельно помечаем те, что уже начаты (есть открытая in_progress-попытка).
        // ────────────────────────────────────────────────────────────────────
        $latestSubmissionByHomework = Submission::query()
            ->where('user_id', $user->id)
            ->whereIn('homework_id', Homework::query()->whereIn('course_id', $courseIds)->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->groupBy('homework_id')
            ->map(fn ($group) => $group->first());

        $homeworksQueue = Homework::query()
            ->whereIn('course_id', $courseIds)
            ->whereNotNull('due_at')
            ->where('due_at', '>=', now()->subDays(30))
            ->with('lesson.courseSession.course')
            ->orderBy('due_at')
            ->get()
            ->reject(fn (Homework $hw) => $hw->isLessonUpcoming())
            ->map(function ($hw) use ($latestSubmissionByHomework) {
                $sub = $latestSubmissionByHomework->get($hw->id);
                $courseTitle = optional($hw->lesson?->courseSession?->course)->title ?? 'Курс';

                $isStarted = $sub && $sub->status === 'in_progress';
                $isOverdue = !$sub && $hw->due_at->isPast();

                return [
                    'homework'   => $hw,
                    'subject'    => preg_replace('/,.*$/u', '', $courseTitle),
                    'is_started' => $isStarted,
                    'is_done'    => $sub && $sub->status !== 'in_progress',
                    'is_overdue' => $isOverdue,
                    'color'      => $isStarted ? 'blue' : ($isOverdue ? 'red' : 'yellow'),
                ];
            })
            ->reject(fn ($row) => $row['is_done'])
            ->take(3)
            ->values();

        // Есть ли просроченные домашки прямо сейчас — влияет на настроение маскота в приветствии
        $overdueCount = collect($days)->flatMap(fn ($d) => $d['items'])
            ->where('status', 'overdue')
            ->count();

        return view('student.dashboard', compact('courses', 'days', 'nextItem', 'homeworksQueue', 'overdueCount', 'blockedCourseIds'));
    }
}
