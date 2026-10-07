<?php

namespace App\Service;

use App\Models\Homework;
use App\Models\HomeworkTask;
use App\Models\Lesson;
use App\Models\LessonView;
use App\Models\Submission;
use App\Models\TaskAttempt;
use App\Models\TaskCriteria;
use App\Models\User;
use App\Models\UserActivityDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Отчёт по ученику для /admin/users/{id} — преподаватель смотрит его перед
 * тем, как записать ученику голосовое. Только читает; все данные собираются
 * в других местах (визард домашки, проверка куратора, плеер урока,
 * TrackStudentActivity).
 *
 * Часть данных копится только с момента выкладки (просмотры уроков, время
 * на задание, неверные проверки, момент сдачи) — для более старых работ и
 * уроков отчёт показывает "нет данных", а не ноль: "не смотрел" и "мы ещё
 * не считали" — разные вещи.
 */
class StudentReport
{
    /** С какой доли просмотра урок считается просмотренным. */
    public const WATCHED_PERCENT = 70;

    /** Ниже этой доли баллов номер/тема попадает в "слабые места". */
    public const WEAK_PERCENT = 60;

    /** Сколько дней показывает календарь активности (5 недель). */
    public const ACTIVITY_DAYS = 35;

    /** @var array<string,int> "category_id|number" => max_score */
    private array $criteriaMax = [];

    public function build(User $user): array
    {
        $this->criteriaMax = TaskCriteria::query()
            ->get(['category_id', 'number', 'max_score'])
            ->mapWithKeys(fn ($c) => [$c->category_id.'|'.$c->number => (int) $c->max_score])
            ->all();

        $courses = $user->courses()
            ->get()
            ->sortBy(fn ($c) => $c->pivot->status === 'active' ? 0 : 1)
            ->values()
            ->map(fn ($course) => $this->courseReport($user, $course));

        $activity = $this->activity($user, $courses);
        $feedback = $user->feedback()->with('author:id,name,first_name,last_name')->limit(30)->get();

        return [
            'courses' => $courses,
            'activity' => $activity,
            'practice' => $this->practice($user),
            'feedback' => $feedback,
            'daysSinceFeedback' => $feedback->first()
                ? (int) $feedback->first()->created_at->startOfDay()->diffInDays(now()->startOfDay())
                : null,
            'headline' => $this->headline($courses, $activity),
        ];
    }

    private function courseReport(User $user, $course): array
    {
        $homeworks = Homework::query()
            ->where('course_id', $course->id)
            ->with(['tasks.task.topic', 'lesson.courseSession'])
            ->get()
            // Те же правила видимости, что и у самого ученика: домашку к ещё
            // не прошедшему уроку или от прошлого потока он не видит — значит,
            // и "не сдал" по ней писать нельзя.
            ->reject(fn (Homework $hw) => $hw->isLessonUpcoming() || $hw->isLessonBeforeEnrollment($user))
            ->sortByDesc(fn (Homework $hw) => $this->homeworkDate($hw)?->timestamp ?? 0)
            ->values();

        $submissions = Submission::query()
            ->where('user_id', $user->id)
            ->whereIn('homework_id', $homeworks->pluck('id'))
            ->orderBy('id')
            ->get()
            ->groupBy('homework_id');

        $rows = $homeworks->map(
            fn (Homework $hw) => $this->homeworkRow($user, $hw, $submissions->get($hw->id, collect()))
        );

        [$numbers, $topics, $comments] = $this->breakdown($rows);

        return [
            'course' => $course,
            'pivot' => $course->pivot,
            'enrolledAt' => $user->courseEnrolledAt($course->id),
            'rows' => $rows,
            'summary' => $this->summary($rows),
            'numbers' => $numbers,
            'topics' => $topics,
            'comments' => $comments,
            'lessons' => $this->lessons($user, $course),
        ];
    }

    private function homeworkDate(Homework $hw): ?Carbon
    {
        return $hw->lesson?->courseSession?->start_date_time ?? $hw->due_at ?? $hw->created_at;
    }

    /**
     * Одна строка "домашка × ученик". Итог считается по последней
     * завершённой попытке — по ней же выставляется оценка куратором
     * (см. Submission::pendingReviewQueue()).
     */
    private function homeworkRow(User $user, Homework $hw, Collection $subs): array
    {
        $finished = $subs->where('status', '!=', 'in_progress')->values();
        $final = $finished->last();
        $inProgress = $subs->firstWhere('status', 'in_progress');

        $max = (int) $hw->tasks->sum(fn (HomeworkTask $t) => $this->maxScore($t));

        $row = [
            'homework' => $hw,
            'date' => $this->homeworkDate($hw),
            'isMock' => $hw->type === 'mock',
            'final' => $final,
            'attempts' => $finished->count(),
            'attemptsAllowed' => $hw->attemptsAllowed(),
            'max' => $max,
            'score' => null,
            'percent' => null,
            'firstPercent' => null,
            'late' => false,
            'submittedAt' => null,
            'submittedAtExact' => false,
            'lastMinute' => false,
            'seconds' => null,
            'wrong' => null,
            'hints' => null,
            'firstTryOk' => null,
            'firstTryTotal' => null,
            'blank' => 0,
        ];

        if (! $final) {
            $row['state'] = $inProgress
                ? 'in_progress'
                : ($hw->isOverdueFor($user) ? 'overdue' : 'not_started');

            return $row;
        }

        $final->setRelation('homework', $hw);

        // 'expired' — сдано после дедлайна; проверка куратора потом меняет
        // его на 'checked' (SubmissionReviewController::finish()), а до неё
        // "проверено ли" в статусе не видно — смотрим на сами задания.
        $row['state'] = match (true) {
            $final->status === 'checked' => 'checked',
            $final->status === 'expired' => $final->allManualTasksClosedForAdmin() ? 'checked' : 'pending',
            default => 'pending',
        };

        $row['score'] = (int) $final->total_score;
        $row['percent'] = $this->percent($row['score'], $max);

        $first = $finished->first();
        if ($finished->count() > 1 && $first->total_score !== null) {
            $row['firstPercent'] = $this->percent((int) $first->total_score, $max);
        }

        // submitted_at пишется только с момента выкладки отчёта; для старых
        // работ показываем время начала попытки и помечаем как неточное.
        $row['submittedAt'] = $final->submitted_at ?? $final->created_at;
        $row['submittedAtExact'] = $final->submitted_at !== null;

        $row['late'] = $final->status === 'expired'
            || ($hw->due_at && $final->submitted_at && $final->submitted_at->gt($hw->due_at));

        // "В последний момент" — за 12 часов до дедлайна и позже.
        $row['lastMinute'] = ! $row['late'] && $hw->due_at && $final->submitted_at
            && $final->submitted_at->gte($hw->due_at->copy()->subHours(12));

        $answers = $final->answers ?? [];
        $row['blank'] = $hw->tasks
            ->filter(fn (HomeworkTask $t) => trim((string) ($answers[$t->id] ?? '')) === '')
            ->count();

        $meta = $final->task_meta;
        if (is_array($meta) && $meta !== []) {
            $row['seconds'] = (int) collect($meta)->sum(fn ($m) => (int) ($m['seconds'] ?? 0));
            $row['wrong'] = (int) collect($meta)->sum(fn ($m) => (int) ($m['wrong'] ?? 0));
            $row['hints'] = collect($meta)->filter(fn ($m) => ! empty($m['hint']))->count();

            // В пробнике результат проверки ученику не показывается, там нет
            // "попыток до верного" — первую проверку считаем только в домашках.
            if (! $row['isMock']) {
                $firsts = collect($meta)->pluck('first')->filter();
                $row['firstTryTotal'] = $firsts->count();
                $row['firstTryOk'] = $firsts->filter(fn ($s) => $s === 'ok')->count();
            }
        }

        return $row;
    }

    /**
     * Разбивка результатов по номерам заданий ЕГЭ и по темам + последние
     * комментарии куратора. Берутся только последние завершённые попытки и
     * только задания с выставленным баллом (не пропущенные куратором и не
     * ждущие проверки).
     */
    private function breakdown(Collection $rows): array
    {
        $numbers = [];
        $topics = [];
        $comments = [];

        foreach ($rows as $row) {
            $final = $row['final'];
            if (! $final) {
                continue;
            }

            $perTask = $final->per_task_results ?? [];
            $meta = $final->task_meta ?? [];

            foreach ($row['homework']->tasks as $task) {
                $result = $perTask[$task->id] ?? null;
                if (! is_array($result) || ! empty($result['skipped']) || ! array_key_exists('score', $result)) {
                    continue;
                }

                $score = (int) $result['score'];
                $max = (int) ($result['max'] ?? $this->maxScore($task));
                $number = (string) ($task->number ?? '');

                if ($number !== '') {
                    $numbers[$number] ??= ['number' => $number, 'score' => 0, 'max' => 0, 'count' => 0, 'manual' => ! $task->isAutoGradable(), 'wrong' => 0];
                    $numbers[$number]['score'] += $score;
                    $numbers[$number]['max'] += $max;
                    $numbers[$number]['count']++;
                    $numbers[$number]['wrong'] += (int) ($meta[$task->id]['wrong'] ?? 0);
                }

                $topic = $task->task?->topic;
                if ($topic) {
                    $topics[$topic->id] ??= ['title' => $topic->title, 'score' => 0, 'max' => 0, 'count' => 0];
                    $topics[$topic->id]['score'] += $score;
                    $topics[$topic->id]['max'] += $max;
                    $topics[$topic->id]['count']++;
                }

                $comment = trim((string) ($result['comment'] ?? ''));
                if ($comment !== '') {
                    $comments[] = [
                        'homework' => $row['homework'],
                        'submission' => $final,
                        'number' => $number,
                        'score' => $score,
                        'max' => $max,
                        'comment' => $comment,
                        'reviewedBy' => $result['reviewed_by'] ?? null,
                        'at' => isset($result['reviewed_at']) ? Carbon::parse($result['reviewed_at']) : $final->updated_at,
                    ];
                }
            }
        }

        $withPercent = fn (array $item) => $item + ['percent' => $this->percent($item['score'], $item['max'])];

        $numbers = collect($numbers)->map($withPercent)
            ->sortBy(fn ($n) => sprintf('%05d|%s', (int) $n['number'], $n['number']))
            ->values();
        $topics = collect($topics)->map($withPercent)->sortBy('percent')->values();

        $comments = collect($comments)->sortByDesc(fn ($c) => $c['at']?->timestamp ?? 0)->take(6)->values();
        $reviewers = User::whereIn('id', $comments->pluck('reviewedBy')->filter()->unique())
            ->get(['id', 'name', 'first_name', 'last_name'])
            ->keyBy('id');
        $comments = $comments->map(fn ($c) => $c + ['reviewer' => $reviewers->get($c['reviewedBy'])]);

        return [$numbers, $topics, $comments];
    }

    private function summary(Collection $rows): array
    {
        $regular = $rows->where('isMock', false);
        $done = $regular->filter(fn ($r) => $r['final'] !== null);
        $scored = $done->filter(fn ($r) => $r['percent'] !== null)->values();

        // $rows отсортированы от новых к старым: три последние работы против
        // трёх предыдущих — растёт ученик или проседает.
        $recent = $scored->take(3);
        $previous = $scored->slice(3, 3);

        $withFirstTry = $done->filter(fn ($r) => $r['firstTryTotal']);

        return [
            'total' => $regular->count(),
            'done' => $done->count(),
            'missing' => $regular->filter(fn ($r) => in_array($r['state'], ['overdue', 'not_started'], true))->values(),
            'overdue' => $regular->where('state', 'overdue')->values(),
            'inProgress' => $regular->where('state', 'in_progress')->count(),
            'pending' => $regular->where('state', 'pending')->count(),
            'late' => $done->where('late', true)->count(),
            'lastMinute' => $done->where('lastMinute', true)->count(),
            'avgPercent' => $scored->isEmpty() ? null : (int) round($scored->avg('percent')),
            'recentPercent' => $recent->isEmpty() ? null : (int) round($recent->avg('percent')),
            'previousPercent' => $previous->count() < 2 ? null : (int) round($previous->avg('percent')),
            'firstTryPercent' => $withFirstTry->isEmpty()
                ? null
                : $this->percent($withFirstTry->sum('firstTryOk'), $withFirstTry->sum('firstTryTotal')),
            'mocks' => $rows->where('isMock', true)->values(),
        ];
    }

    /**
     * Прошедшие уроки курса и сколько ученик из них посмотрел.
     */
    private function lessons(User $user, $course): array
    {
        $enrolledAt = $user->courseEnrolledAt($course->id);

        $lessons = Lesson::query()
            ->whereHas('courseSession', fn ($q) => $q->where('course_id', $course->id))
            ->with('courseSession')
            ->get();

        $views = LessonView::where('user_id', $user->id)
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->get()
            ->groupBy('lesson_id');

        // Учёт просмотров начался не с первого урока курса: всё, что было
        // раньше первой записи в lesson_views, — "нет данных", а не "не смотрел".
        $trackingSince = LessonView::min('created_at');
        $trackingSince = $trackingSince ? Carbon::parse($trackingSince) : null;

        $rows = $lessons
            ->map(function (Lesson $lesson) use ($views, $enrolledAt, $trackingSince) {
                $start = $lesson->courseSession?->start_date_time;
                $byKind = $views->get($lesson->id, collect())->keyBy('kind');

                if (! $start || $start->isFuture()) {
                    return null;
                }
                // Урок до зачисления показываем, только если ученик его всё же открыл.
                if ($enrolledAt && $start->lt($enrolledAt) && $byKind->isEmpty()) {
                    return null;
                }

                $recording = $byKind->get(LessonView::KIND_RECORDING);
                $live = $byKind->get(LessonView::KIND_LIVE);
                $short = $byKind->get(LessonView::KIND_SHORT);

                $liveMinutes = $live ? (int) round($live->watched_seconds / 60) : 0;
                $sessionMinutes = (int) ($lesson->courseSession->duration_minutes ?? 0);
                $livePercent = $live && $sessionMinutes > 0
                    ? (int) min(100, round($liveMinutes / $sessionMinutes * 100))
                    : null;

                $best = max($recording?->percent() ?? 0, $livePercent ?? 0);
                $hasVideo = filled($lesson->recording_link) || filled($lesson->meet_link);

                if ($best >= self::WATCHED_PERCENT) {
                    $state = 'watched';
                } elseif ($recording || $live) {
                    $state = 'partial';
                } elseif (! $hasVideo) {
                    $state = 'no_video';
                } elseif (! $trackingSince || $start->lt($trackingSince)) {
                    $state = 'no_data';
                } else {
                    $state = 'missed';
                }

                return [
                    'lesson' => $lesson,
                    'date' => $start,
                    'state' => $state,
                    'recordingPercent' => $recording?->percent(),
                    'recordingMinutes' => $recording ? (int) round($recording->watched_seconds / 60) : null,
                    'liveMinutes' => $live ? $liveMinutes : null,
                    'shortPercent' => $short?->percent(),
                    'shortMinutes' => $short ? (int) round($short->watched_seconds / 60) : null,
                    'hasShort' => filled($lesson->short_class),
                    'notesOpened' => $byKind->has(LessonView::KIND_NOTES),
                    'hasNotes' => filled($lesson->notes_link),
                    'lastWatchedAt' => $byKind->max('last_watched_at'),
                ];
            })
            ->filter()
            ->sortByDesc(fn ($r) => $r['date']->timestamp)
            ->values();

        $tracked = $rows->whereIn('state', ['watched', 'partial', 'missed']);

        return [
            'rows' => $rows,
            'tracked' => $tracked->count(),
            'watched' => $tracked->where('state', 'watched')->count(),
            'missed' => $tracked->where('state', 'missed')->values(),
        ];
    }

    /**
     * Календарь активности за ACTIVITY_DAYS дней. Два источника: точный
     * (user_activity_days, копится с момента выкладки) и косвенный — дни,
     * в которые ученик начинал домашку или решал задания из банка; по нему
     * видно историю и до включения учёта.
     */
    private function activity(User $user, Collection $courses): array
    {
        $from = now()->startOfDay()->subDays(self::ACTIVITY_DAYS - 1);

        $tracked = UserActivityDay::where('user_id', $user->id)
            ->where('date', '>=', $from->toDateString())
            ->get()
            ->keyBy(fn ($d) => $d->date->toDateString());

        $workDays = Submission::where('user_id', $user->id)->where('created_at', '>=', $from)->pluck('created_at')
            ->merge(TaskAttempt::where('user_id', $user->id)->where('created_at', '>=', $from)->pluck('created_at'))
            ->map(fn ($at) => Carbon::parse($at)->toDateString())
            ->unique()
            ->flip();

        $days = [];
        for ($i = 0; $i < self::ACTIVITY_DAYS; $i++) {
            $date = $from->copy()->addDays($i);
            $key = $date->toDateString();
            $row = $tracked->get($key);

            $days[] = [
                'date' => $date,
                'minutes' => $row?->minutes(),
                'level' => match (true) {
                    $row === null => $workDays->has($key) ? 1 : 0,
                    $row->minutes() >= 60 => 3,
                    $row->minutes() >= 20 => 2,
                    default => 1,
                },
            ];
        }

        // Последняя активность: точная отметка, иначе — самое позднее из
        // косвенных следов (визит на дашборд, начатая работа, задание из банка).
        $lastSeen = UserActivityDay::where('user_id', $user->id)->max('last_seen_at');
        $lastSeen = $lastSeen ? Carbon::parse($lastSeen) : null;
        $lastSeenExact = $lastSeen !== null;

        if (! $lastSeen) {
            $lastSeen = collect([
                $user->fish_last_active_date,
                Submission::where('user_id', $user->id)->max('created_at'),
                TaskAttempt::where('user_id', $user->id)->max('created_at'),
            ])->filter()->map(fn ($at) => Carbon::parse($at))->max();
        }

        $activeDays = fn (int $n) => collect($days)->slice(-$n)->filter(fn ($d) => $d['level'] > 0)->count();

        return [
            'days' => $days,
            'lastSeen' => $lastSeen,
            'lastSeenExact' => $lastSeenExact,
            'daysSince' => $lastSeen ? (int) $lastSeen->copy()->startOfDay()->diffInDays(now()->startOfDay()) : null,
            'activeDays7' => $activeDays(7),
            'activeDays14' => $activeDays(14),
            'minutes7' => (int) collect($days)->slice(-7)->sum(fn ($d) => (int) $d['minutes']),
            'hasTracked' => $tracked->isNotEmpty(),
        ];
    }

    /**
     * Самостоятельная практика — задания из банка, решённые вне домашек.
     */
    private function practice(User $user): array
    {
        $since = now()->subDays(30);
        $attempts = TaskAttempt::where('user_id', $user->id)->get(['task_id', 'status', 'created_at']);
        $recent = $attempts->filter(fn ($a) => $a->created_at && $a->created_at->gte($since));
        $graded = $recent->whereNotNull('status');

        return [
            'total' => $attempts->count(),
            'recent' => $recent->count(),
            'recentTasks' => $recent->pluck('task_id')->unique()->count(),
            'recentOkPercent' => $graded->isEmpty()
                ? null
                : $this->percent($graded->where('status', 'ok')->count(), $graded->count()),
            'lastAt' => $attempts->max('created_at'),
        ];
    }

    /**
     * Короткая выжимка "о чём сказать" — то, ради чего преподаватель
     * открывает страницу. Каждая строка: tone (good|warn|bad|info) + text.
     */
    private function headline(Collection $courses, array $activity): array
    {
        $lines = [];

        if ($activity['daysSince'] === null) {
            $lines[] = ['tone' => 'bad', 'text' => 'Ни разу не был активен на платформе.'];
        } elseif ($activity['daysSince'] >= 7) {
            $lines[] = ['tone' => 'bad', 'text' => 'Не заходил '.$this->plural($activity['daysSince'], ['день', 'дня', 'дней']).'.'];
        } elseif ($activity['activeDays14'] >= 8) {
            $lines[] = ['tone' => 'good', 'text' => 'Занимается регулярно: '.$this->plural($activity['activeDays14'], ['активный день', 'активных дня', 'активных дней']).' за две недели.'];
        } else {
            $lines[] = ['tone' => 'info', 'text' => 'За две недели '.$this->plural($activity['activeDays14'], ['активный день', 'активных дня', 'активных дней']).'.'];
        }

        foreach ($courses as $report) {
            if ($report['pivot']->status !== 'active') {
                continue;
            }

            $prefix = $courses->where('pivot.status', 'active')->count() > 1 ? $report['course']->title.': ' : '';
            $s = $report['summary'];

            if ($s['total'] > 0) {
                $missing = $s['missing']->count();
                $lines[] = [
                    'tone' => $missing === 0 ? 'good' : ($s['overdue']->isNotEmpty() ? 'bad' : 'warn'),
                    'text' => $prefix.'сдано домашек '.$s['done'].' из '.$s['total']
                        .($s['overdue']->isNotEmpty()
                            ? ', просрочено и не сдано: '.$s['overdue']->take(3)->map(fn ($r) => '«'.$r['homework']->title.'»')->implode(', ')
                                .($s['overdue']->count() > 3 ? ' и ещё '.($s['overdue']->count() - 3) : '')
                            : '')
                        .'.',
                ];
            }

            if ($s['recentPercent'] !== null && $s['previousPercent'] !== null) {
                $delta = $s['recentPercent'] - $s['previousPercent'];
                if (abs($delta) >= 10) {
                    $lines[] = [
                        'tone' => $delta > 0 ? 'good' : 'bad',
                        'text' => $prefix.'результаты '.($delta > 0 ? 'растут' : 'падают').': последние работы — '
                            .$s['recentPercent'].'%, до этого — '.$s['previousPercent'].'%.',
                    ];
                }
            }

            $weak = $report['numbers']
                ->filter(fn ($n) => $n['count'] >= 2 && $n['percent'] !== null && $n['percent'] < self::WEAK_PERCENT)
                ->sortBy('percent')
                ->take(3);
            if ($weak->isNotEmpty()) {
                $lines[] = [
                    'tone' => 'warn',
                    'text' => $prefix.'слабые номера: '.$weak->map(fn ($n) => '№'.$n['number'].' ('.$n['percent'].'%)')->implode(', ').'.',
                ];
            }

            $lessons = $report['lessons'];
            if ($lessons['tracked'] > 0) {
                $missed = $lessons['missed']->count();
                $lines[] = [
                    'tone' => $missed === 0 ? 'good' : ($missed * 2 >= $lessons['tracked'] ? 'bad' : 'warn'),
                    'text' => $prefix.'посмотрел уроков '.$lessons['watched'].' из '.$lessons['tracked']
                        .($missed > 0 ? ', не открывал: '.$missed : '').'.',
                ];
            }
        }

        return $lines;
    }

    /**
     * Максимальный балл за задание без запроса на каждое: аксессор
     * Task::max_score сам ходит в task_criteria, на странице с десятками
     * домашек это были бы сотни запросов. Правила те же, что в
     * HomeworkTask::getAttribute(): свой max_score в домашке важнее
     * значения из банка.
     */
    private function maxScore(HomeworkTask $task): int
    {
        $local = $task->getRawOriginal('max_score');
        if ($local !== null) {
            return (int) $local;
        }

        $bank = $task->getRawOriginal('task_id') ? $task->task : null;
        if ($bank) {
            return $this->criteriaMax[$bank->category_id.'|'.$bank->number] ?? 1;
        }

        return 1;
    }

    private function percent(int|float $score, int|float $max): ?int
    {
        return $max > 0 ? (int) round($score / $max * 100) : null;
    }

    private function plural(int $n, array $forms): string
    {
        $mod10 = $n % 10;
        $mod100 = $n % 100;

        $index = match (true) {
            $mod10 === 1 && $mod100 !== 11 => 0,
            $mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14) => 1,
            default => 2,
        };

        return $n.' '.$forms[$index];
    }
}
