<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Lesson;
use App\Models\Homework;
use App\Models\Submission;
use App\Service\BillingService;
use App\Service\FishFoodService;


class DashboardController extends Controller
{
    public function __invoke(BillingService $billing, FishFoodService $fish)
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

        // Сессии, для которых время уже назначено, а сам урок (тема/контент)
        // ещё не создан — раньше такой день выглядел как "Выходной", хотя
        // занятие фактически есть, просто тема пока не заведена.
        $sessionsWithoutLesson = CourseSession::query()
            ->whereIn('course_id', $courseIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereDoesntHave('lesson')
            ->with('course')
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

    // Тип и цвет — ключи семантические (theory/practice/...), сама
    // Apple-палитра под ними — в dashboard.blade.php ($bgMap/$borderMap/$textMap).
    $type  = $lesson->lesson_type === 'practice' ? 'Практика' : 'Теория';
    $color = $lesson->lesson_type === 'practice' ? 'practice' : 'theory';

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

// Сессии без урока — занятие назначено, но тема ещё не заведена
foreach ($sessionsWithoutLesson as $session) {
    $dKey = Carbon::parse($session->date)->toDateString();
    if (!isset($daysMap[$dKey])) continue;

    $courseTitle = optional($session->course)->title ?? 'Курс';
    $subject     = preg_replace('/,.*$/u', '', $courseTitle);

    $daysMap[$dKey]['items'][] = [
        'type'    => 'Урок',
        'subject' => $subject,
        'title'   => 'Тема пока неизвестна',
        'time'    => $session->start_time ? mb_substr($session->start_time, 0, 5) : '—:—',
        'color'   => 'theory',
        'status'  => null,
        // Ни lesson, ни homework — Blade отрисует обычный текст без ссылки
        // (переходить пока некуда). 'session' нужен только для сортировки
        // по времени внутри дня, см. ниже.
        'session' => $session,
    ];
}


        // Домашки/Пробники
        foreach ($homeworks as $hw) {
            $due = $hw->due_at ? Carbon::parse($hw->due_at) : null;
            if (!$due) continue;

            $dKey = $due->toDateString();
            if (!isset($daysMap[$dKey])) continue;

            // "Выполнена" — только если попытка реально отправлена (checked/
            // pending/expired), а не просто начата: $subs->isNotEmpty() раньше
            // засчитывал и брошенный in_progress без единого ответа — карточка
            // красилась в зелёный и подписывалась "Выполнена" для домашки,
            // которую фактически даже не открывали до конца. Отдельного
            // статуса "начато, но не отправлено" не вводим — такая домашка
            // просто выглядит как обычная непройденная (или просроченная,
            // если дедлайн уже прошёл).
            $subs = $userSubmissions->get($hw->id) ?? collect();
            $isCompleted = $subs->contains(fn (Submission $s) => $s->status !== 'in_progress');
            $isOverdue   = !$isCompleted && $due->isPast();

            $subjectCourseTitle = optional($hw->lesson?->courseSession?->course)->title ?? 'Курс';
            $subjectClean       = preg_replace('/,.*$/u', '', $subjectCourseTitle);

            // Просрочено/выполнено — это перекрашивает всю карточку целиком
            // (приоритет над цветом типа домашка/пробник), а не просто
            // тускнеет/помечается значком поверх исходного цвета.
            if ($isOverdue) {
                $color = 'overdue';
            } elseif ($isCompleted) {
                $color = 'completed';
            } else {
                $color = $hw->type === 'mock' ? 'mock' : 'homework';
            }

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

        // Точное время пункта расписания (для сортировки внутри дня и для
        // отбора «ближайших событий» ниже) — домашка по due_at, урок по
        // старту сессии. Один источник истины вместо двух отдельных копий
        // этой логики.
        $itemTimestamp = function ($it) {
            if (!empty($it['homework']) && $it['homework'] instanceof \App\Models\Homework) {
                $due = $it['homework']->due_at ? \Illuminate\Support\Carbon::parse($it['homework']->due_at) : null;
                return $due ? $due->timestamp : null;
            }

            if (!empty($it['lesson']) && $it['lesson'] instanceof \App\Models\Lesson) {
                $lesson  = $it['lesson'];
                $session = $lesson->courseSession ?? null;
                $dt = null;

                if ($session) {
                    if (!empty($session->start_at)) {
                        try { $dt = \Illuminate\Support\Carbon::parse($session->start_at); } catch (\Throwable $e) {}
                    }
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

                if (!$dt) {
                    $dateStr = $lesson->display_date ?? null;
                    $timeStr = $lesson->display_time ?? null;
                    if ($dateStr && $timeStr) {
                        try { $dt = \Illuminate\Support\Carbon::parse($dateStr.' '.$timeStr); } catch (\Throwable $e) {}
                    } elseif ($dateStr) {
                        try { $dt = \Illuminate\Support\Carbon::parse($dateStr.' 00:00'); } catch (\Throwable $e) {}
                    }
                }

                return $dt ? $dt->timestamp : null;
            }

            if (!empty($it['session']) && $it['session'] instanceof \App\Models\CourseSession) {
                try {
                    return \Illuminate\Support\Carbon::parse($it['session']->date . ' ' . ($it['session']->start_time ?? '00:00'))->timestamp;
                } catch (\Throwable $e) {}
            }

            return null;
        };

        // Сортируем карточки каждого дня по времени (уроки — по старту сессии, домашки — по дедлайну)
        foreach ($daysMap as $dateKey => &$dayRow) {
            $dayRow['items'] = collect($dayRow['items'] ?? [])
                ->sortBy(fn ($it) => $itemTimestamp($it) ?? PHP_INT_MAX)
                ->values()->all();
        }

        $days = array_values($daysMap);

        // ────────────────────────────────────────────────────────────────────
        // Карточка «Ближайшие события»: отдельно ближайший ещё не прошедший
        // урок и отдельно ближайшая ещё не сданная домашка (каждый — первый
        // подходящий пункт начиная с сегодняшнего дня; $days уже отсортирован
        // по времени внутри дня выше). Сюда не должны попадать ни просроченные
        // домашки, ни уже прошедшие сегодня уроки — только то, что правда
        // ещё впереди: 'completed'/'overdue' статус исключаем явно, и
        // дополнительно сверяем реальное время пункта с now() — день "сегодня"
        // сам по себе не гарантирует, что конкретный час ещё не прошёл.
        // ────────────────────────────────────────────────────────────────────
        $nextLesson = null;
        $nextHomework = null;
        $todayIndex = null;
        foreach ($days as $i => $d) {
            if (!empty($d['is_today'])) { $todayIndex = $i; break; }
        }
        if ($todayIndex !== null) {
            $nowTs = now()->timestamp;
            for ($i = $todayIndex; $i < count($days); $i++) {
                foreach ($days[$i]['items'] as $it) {
                    if (in_array($it['status'] ?? null, ['completed', 'overdue'], true)) {
                        continue;
                    }
                    $ts = $itemTimestamp($it);
                    if ($ts !== null && $ts <= $nowTs) {
                        continue;
                    }
                    if ($nextLesson === null && (!empty($it['lesson']) || !empty($it['session']))) {
                        $nextLesson = $it + ['day' => $days[$i]['day'], 'date' => $days[$i]['date']];
                    }
                    if ($nextHomework === null && !empty($it['homework'])) {
                        $nextHomework = $it + ['day' => $days[$i]['day'], 'date' => $days[$i]['date']];
                    }
                    if ($nextLesson !== null && $nextHomework !== null) {
                        break 2;
                    }
                }
            }
        }

        // ────────────────────────────────────────────────────────────────────
        // Карточка «Оплата»: ближайший предстоящий платёж среди курсов на
        // регулярной оплате (billing_interval_days задан) — просроченные
        // приоритетнее (даже если про них уже есть баннер в шапке, полезно
        // видеть их и здесь), иначе — тот, что должен списаться раньше всех.
        // Курсы без регулярной оплаты (промокод/разовый доступ) сюда не
        // попадают — им нечего показывать.
        // ────────────────────────────────────────────────────────────────────
        $nextPayment = $courses
            ->filter(fn (Course $course) => $billing->isBillingEnabled($user, $course))
            ->map(function (Course $course) use ($billing, $user) {
                $due = $billing->nextDueDate($user, $course);
                if (!$due) {
                    return null;
                }

                return [
                    'course'          => $course,
                    'due'             => $due,
                    'daysLeft'        => $billing->dueInDays($user, $course),
                    'isOverdue'       => $billing->isPastDue($user, $course),
                    'isPromiseActive' => $billing->isPromiseActive($user, $course),
                ];
            })
            ->filter()
            ->sortBy(fn (array $row) => $row['isOverdue'] ? (PHP_INT_MIN + $row['due']->timestamp) : $row['due']->timestamp)
            ->first();

        // Заход на дашборд — это и есть «визит на платформу» для ежедневного
        // бонуса корма (не чаще раза в день, см. FishFoodService::awardDailyVisit()).
        $fish->awardDailyVisit($user);
        $fishLevel = $fish->levelFor((int) $user->fish_total_fed);
        $fishProgress = $fish->progressFor((int) $user->fish_total_fed);
        $fishBalance = (int) $user->fish_corm_balance;
        $fishMascotImage = $fish->mascotImageUrl($fishLevel);
        $fishMascotEatingImage = $fish->mascotImageUrl($fishLevel, 'eating');
        $fishBackgroundImage = $fish->backgroundImageUrl($user->fish_background);
        $fishName = $user->fish_name ?: $fish->levelName($fishLevel);

        return view('student.dashboard', compact('courses', 'days', 'nextLesson', 'nextHomework', 'nextPayment', 'blockedCourseIds', 'fishLevel', 'fishProgress', 'fishBalance', 'fishMascotImage', 'fishMascotEatingImage', 'fishBackgroundImage', 'fishName'));
    }
}
