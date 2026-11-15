<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class IndexController extends Controller
{
    /**
     * Ключи статусов, которые вообще могут встретиться в основном списке CRM
     * (completed/lost сюда не попадают — они уходят в архив, см.
     * User::isClosedInCrm()). Порядок — как в User::crmStatusOptions(), это
     * же порядок пунктов в фильтре и в блоке счётчиков.
     */
    private const PIPELINE_STATUS_KEYS = ['new', 'contacted', 'trial_done', 'active', 'past_due', 'frozen'];

    /**
     * Варианты сортировки списка (значение => подпись в селекте). 'urgency' —
     * значение по умолчанию, не хранит отдельный ключ в query string
     * (см. $sort ?: 'urgency' ниже), чтобы обычная ссылка на /admin/crm без
     * параметров вела на неё же.
     */
    private const SORT_OPTIONS = [
        'urgency' => 'По срочности',
        'created_desc' => 'Сначала новые',
        'created_asc' => 'Сначала старые',
        'name' => 'По имени (А-Я)',
    ];

    public function __invoke(Request $request)
    {
        $q = trim($request->string('q')->toString());
        $status = trim($request->string('status')->toString());
        $sort = trim($request->string('sort')->toString()) ?: 'urgency';
        $soonOnly = $request->boolean('soon');
        $remindersOnly = $request->boolean('reminders');
        $remindedOnly = $request->boolean('reminded');
        $dateFrom = trim($request->string('date_from')->toString());
        $dateTo = trim($request->string('date_to')->toString());

        if (! array_key_exists($sort, self::SORT_OPTIONS)) {
            $sort = 'urgency';
        }

        // Чипы над списком (статусы + «Скоро истекает»/«Уведомления»/
        // «Напомнил, не оплатил») — один быстрый фильтр за раз, не
        // комбинируются. Если в ссылке пришло несколько — побеждает первый.
        if (! in_array($status, self::PIPELINE_STATUS_KEYS, true)) {
            $status = '';
        }
        if ($status !== '') {
            $soonOnly = $remindersOnly = $remindedOnly = false;
        } elseif ($soonOnly) {
            $remindersOnly = $remindedOnly = false;
        } elseif ($remindersOnly) {
            $remindedOnly = false;
        }

        // isClosedInCrm() (completed/lost) зависит от статусов курсов и
        // ручного crm_stage вперемешку — не сводится к простому WHERE без
        // риска разъехаться с App\Models\User::crmStatus(). CRM у одной
        // школы — это десятки-сотни, не миллионы строк, поэтому фильтруем
        // и пагинируем уже в памяти, одним источником правды на статус.
        //
        // Поиск/фильтры намеренно НЕ идут в этот запрос: счётчики над
        // списком считаются по всей базе (см. ниже).
        $base = User::query()
            ->where('role', User::ROLE_READER)
            ->visibleInCrm()
            ->with([
                'courses',
                // Отсортированы по дате платежа заранее — для каждого курса берём
                // firstWhere('course_id', ...) в шаблоне, без доп. запросов на строку.
                'payments' => fn ($query) => $query->orderByDesc('paid_at'),
                'crmReminders',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->reject(fn (User $u) => $u->isClosedInCrm())
            ->values();

        // Счётчики "сколько учеников в каждом статусе"/"скоро истекает" — по
        // той же полной базе, до поиска/фильтров, чтобы цифры над списком не
        // менялись от того, что сейчас введено в поиске.
        $statusCounts = array_fill_keys(self::PIPELINE_STATUS_KEYS, 0);
        $soonCount = 0;
        $reminderCount = 0;
        $remindedCount = 0;
        foreach ($base as $student) {
            $key = $student->crmStatus()['key'];
            if (array_key_exists($key, $statusCounts)) {
                $statusCounts[$key]++;
            }
            if ($student->crmExpiresSoon()) {
                $soonCount++;
            }
            if ($student->hasActiveCrmReminder()) {
                $reminderCount++;
            }
            if ($student->crmRemindedUnpaid()) {
                $remindedCount++;
            }
        }

        // «В работе» в блоке статистики — только те, с кем сейчас реально
        // работают: та же база CRM (без неподтверждённых регистраций-ботов,
        // без архива completed/lost), минус замороженные.
        $totalUsers = array_sum($statusCounts) - $statusCounts['frozen'];

        // Даты вводятся руками через ?date_from=/&date_to= (не только через
        // <input type="date">), поэтому парсим защищённо — битая дата не
        // должна валить страницу 500-й, просто не даёт фильтрации.
        $dateFromParsed = null;
        if ($dateFrom !== '') {
            try {
                $dateFromParsed = \Carbon\Carbon::parse($dateFrom)->startOfDay();
            } catch (\Exception $e) {
                $dateFrom = '';
            }
        }
        $dateToParsed = null;
        if ($dateTo !== '') {
            try {
                $dateToParsed = \Carbon\Carbon::parse($dateTo)->endOfDay();
            } catch (\Exception $e) {
                $dateTo = '';
            }
        }

        $all = $base
            ->filter(function (User $u) use ($q) {
                if ($q === '') {
                    return true;
                }

                foreach ([$u->name, $u->email, $u->phone, $u->first_name, $u->last_name] as $field) {
                    if ($field !== null && mb_stripos($field, $q) !== false) {
                        return true;
                    }
                }

                return false;
            })
            ->when($status !== '', fn ($c) => $c->filter(fn (User $u) => $u->crmStatus()['key'] === $status))
            ->when($soonOnly, fn ($c) => $c->filter(fn (User $u) => $u->crmExpiresSoon()))
            ->when($remindersOnly, fn ($c) => $c->filter(fn (User $u) => $u->hasActiveCrmReminder()))
            ->when($remindedOnly, fn ($c) => $c->filter(fn (User $u) => $u->crmRemindedUnpaid()))
            ->when($dateFromParsed, fn ($c) => $c->filter(fn (User $u) => $u->created_at && $u->created_at->greaterThanOrEqualTo($dateFromParsed)))
            ->when($dateToParsed, fn ($c) => $c->filter(fn (User $u) => $u->created_at && $u->created_at->lessThanOrEqualTo($dateToParsed)))
            ->values();

        $all = $this->applySort($all, $sort);

        $perPage = 20;
        $page = (int) $request->input('page', 1);
        $students = new LengthAwarePaginator(
            $all->forPage($page, $perPage)->values(),
            $all->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $statusOptions = array_intersect_key(User::crmStatusOptions(), array_flip(self::PIPELINE_STATUS_KEYS));

        // Параметры, которые чип сохраняет при клике (поиск, сортировка,
        // даты), — БЕЗ самих чип-фильтров: чипы взаимоисключающие, клик по
        // одному снимает другой.
        $filterParams = array_filter([
            'q' => $q ?: null,
            'sort' => $sort !== 'urgency' ? $sort : null,
            'date_from' => $dateFrom ?: null,
            'date_to' => $dateTo ?: null,
        ]);

        return view('admin.crm.index', [
            'students' => $students,
            'q' => $q,
            'status' => $status,
            'sort' => $sort,
            'sortOptions' => self::SORT_OPTIONS,
            'soonOnly' => $soonOnly,
            'soonCount' => $soonCount,
            'remindersOnly' => $remindersOnly,
            'reminderCount' => $reminderCount,
            'remindedOnly' => $remindedOnly,
            'remindedCount' => $remindedCount,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'filterParams' => $filterParams,
            'statusOptions' => $statusOptions,
            'statusCounts' => $statusCounts,
            'totalUsers' => $totalUsers,
            'courseStats' => $this->courseStats($base),
            'monthlyRevenueRub' => $this->revenueBetween(now()->startOfMonth(), now()) / 100,
            // Прошлый месяц за тот же отрезок (1-е … сегодняшнее число) —
            // честное сравнение посреди месяца; и за весь прошлый месяц.
            'prevSamePeriodRevenueRub' => $this->revenueBetween(
                now()->subMonthNoOverflow()->startOfMonth(),
                now()->subMonthNoOverflow()->min(now()->subMonthNoOverflow()->endOfMonth())
            ) / 100,
            'prevMonthRevenueRub' => $this->revenueBetween(
                now()->subMonthNoOverflow()->startOfMonth(),
                now()->subMonthNoOverflow()->endOfMonth()
            ) / 100,
        ]);
    }

    /**
     * Сортировка списка. 'urgency' (по умолчанию) не трогаем — там нужный
     * приоритет уже стоит на первом месте через crmSortPriority(). Для
     * остальных трёх режимов явно пришпиливаем "Есть активное напоминание"
     * и следом "Просрочена оплата" наверх поверх выбранного порядка —
     * иначе, отсортировав "по имени" или "по дате", менеджер мог бы
     * случайно закопать самого срочного ученика где-то в середине списка.
     * Не переиспользуем crmSortPriority() целиком (у неё 5 уровней) —
     * здесь нужен именно троичный пин, чтобы всё остальное осталось в
     * выбранном порядке. Работает через два последовательных sortBy —
     * PHP 8+ гарантирует стабильность сортировки, поэтому второй проход
     * не портит порядок, заданный первым проходом внутри каждой группы.
     */
    private function applySort($all, string $sort)
    {
        $priority = fn (User $u) => $u->hasActiveCrmReminder() ? 0 : ($u->crmStatus()['key'] === 'past_due' ? 1 : 2);

        return match ($sort) {
            'created_desc' => $all->sortByDesc('created_at')->sortBy($priority)->values(),
            'created_asc' => $all->sortBy('created_at')->sortBy($priority)->values(),
            'name' => $all
                ->sortBy(fn (User $u) => mb_strtolower(trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: ($u->name ?? '')))
                ->sortBy($priority)
                ->values(),
            default => $all->sortBy(fn (User $u) => $u->crmSortPriority())->values(),
        };
    }

    /**
     * Сколько учеников с активным доступом на каждом курсе + сколько это
     * уникальных людей (ученик на двух курсах — один человек). Считаем по
     * той же базе CRM, что и счётчики: без демо, фейков и архива. Денег по
     * курсу отдельно нет — при пакетной оплате (AccessController) вся сумма
     * пишется на один курс из пары, разбивка была бы неточной.
     */
    private function courseStats($base): array
    {
        $perCourse = [];
        $unique = 0;
        foreach ($base as $student) {
            $active = $student->courses->filter(fn ($c) => $c->pivot->status === 'active');
            if ($active->isNotEmpty()) {
                $unique++;
            }
            foreach ($active as $course) {
                $perCourse[$course->id] ??= ['title' => $course->title, 'students' => 0];
                $perCourse[$course->id]['students']++;
            }
        }

        usort($perCourse, fn ($a, $b) => $b['students'] <=> $a['students']);

        return ['courses' => $perCourse, 'unique' => $unique];
    }

    /** Доход (подтверждённые оплаты, без обещанных) за отрезок. */
    private function revenueBetween($from, $to): int
    {
        return (int) Payment::query()
            ->where('status', 'succeeded')
            ->where('is_promise', false)
            ->whereBetween('paid_at', [$from, $to])
            // Оплаты своих/тестовых аккаунтов в доход не идут.
            ->whereHas('user', fn ($q) => $q->where('is_demo', false)->where('crm_excluded', false))
            ->sum('amount_cents');
    }
}
