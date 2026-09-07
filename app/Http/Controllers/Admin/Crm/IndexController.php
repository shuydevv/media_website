<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Course;
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
        $dateFrom = trim($request->string('date_from')->toString());
        $dateTo = trim($request->string('date_to')->toString());

        if (! array_key_exists($sort, self::SORT_OPTIONS)) {
            $sort = 'urgency';
        }

        // isClosedInCrm() (completed/lost) зависит от статусов курсов и
        // ручного crm_stage вперемешку — не сводится к простому WHERE без
        // риска разъехаться с App\Models\User::crmStatus(). CRM у одной
        // школы — это десятки-сотни, не миллионы строк, поэтому фильтруем
        // и пагинируем уже в памяти, одним источником правды на статус.
        //
        // Поиск/фильтр по статусу намеренно НЕ идут в этот запрос: номер
        // ученика (ниже) должен быть его позицией среди ВСЕХ, кто сейчас в
        // CRM, а не среди только тех, кто прошёл текущий поиск — иначе
        // номера "скакали" бы при каждом вводе в поле поиска.
        $base = User::query()
            ->where('role', User::ROLE_READER)
            ->visibleInCrm()
            ->with([
                'courses',
                // Отсортированы по дате платежа заранее — для каждого курса берём
                // firstWhere('course_id', ...) в шаблоне, без доп. запросов на строку.
                'payments' => fn ($query) => $query->orderByDesc('paid_at'),
            ])
            ->orderByDesc('created_at')
            ->get()
            ->reject(fn (User $u) => $u->isClosedInCrm())
            ->values();

        // Номер ("снизу вверх") — это позиция по хронологии регистрации среди
        // всех, кто сейчас в CRM, стабильный ярлык "какой ты по счёту", а не
        // позиция на экране — считаем его ДО поиска/фильтра по статусу и ДО
        // пересортировки по срочности, иначе номера скакали бы при каждом
        // запросе.
        $baseTotal = $base->count();
        foreach ($base as $index => $student) {
            $student->crmNumber = $baseTotal - $index;
        }

        // Счётчики "сколько учеников в каждом статусе"/"скоро истекает" — по
        // той же полной базе, до поиска/фильтров, чтобы цифры над списком не
        // менялись от того, что сейчас введено в поиске.
        $statusCounts = array_fill_keys(self::PIPELINE_STATUS_KEYS, 0);
        $soonCount = 0;
        foreach ($base as $student) {
            $key = $student->crmStatus()['key'];
            if (array_key_exists($key, $statusCounts)) {
                $statusCounts[$key]++;
            }
            if ($student->crmExpiresSoon()) {
                $soonCount++;
            }
        }

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

        // Общие для чипов-фильтров параметры (без 'page' и без того
        // параметра, который переключает сам чип) — чтобы клик по одному
        // фильтру не сбрасывал остальные уже выбранные.
        $filterParams = array_filter([
            'q' => $q ?: null,
            'status' => $status ?: null,
            'sort' => $sort !== 'urgency' ? $sort : null,
            'date_from' => $dateFrom ?: null,
            'date_to' => $dateTo ?: null,
            'soon' => $soonOnly ? 1 : null,
        ]);

        return view('admin.crm.index', [
            'students' => $students,
            'q' => $q,
            'status' => $status,
            'sort' => $sort,
            'sortOptions' => self::SORT_OPTIONS,
            'soonOnly' => $soonOnly,
            'soonCount' => $soonCount,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'filterParams' => $filterParams,
            'statusOptions' => $statusOptions,
            'statusCounts' => $statusCounts,
            'totalUsers' => User::where('role', User::ROLE_READER)->count(),
            'courseStats' => $this->courseStats(),
            'monthlyRevenueRub' => $this->monthlyRevenue() / 100,
        ]);
    }

    /**
     * Сортировка списка. 'urgency' (по умолчанию) не трогаем — там просрочка
     * уже стоит на первом месте через crmSortPriority(). Для остальных трёх
     * режимов явно пришпиливаем "Просрочена оплата" наверх поверх выбранного
     * порядка — иначе, отсортировав "по имени" или "по дате", менеджер мог
     * бы случайно закопать самого срочного ученика где-то в середине списка.
     * Работает через два последовательных sortBy — PHP 8+ гарантирует
     * стабильность сортировки, поэтому второй проход (по флагу "просрочен")
     * не портит порядок, заданный первым проходом внутри каждой группы.
     */
    private function applySort($all, string $sort)
    {
        $pastDueFirst = fn (User $u) => $u->crmStatus()['key'] === 'past_due' ? 0 : 1;

        return match ($sort) {
            'created_desc' => $all->sortByDesc('created_at')->sortBy($pastDueFirst)->values(),
            'created_asc' => $all->sortBy('created_at')->sortBy($pastDueFirst)->values(),
            'name' => $all
                ->sortBy(fn (User $u) => mb_strtolower(trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: ($u->name ?? '')))
                ->sortBy($pastDueFirst)
                ->values(),
            default => $all->sortBy(fn (User $u) => $u->crmSortPriority())->values(),
        };
    }

    /**
     * Сколько активных учеников на каждом курсе. Намеренно БЕЗ денег по
     * курсу отдельно — при пакетной оплате (см. AccessController) вся сумма
     * пишется на один курс из пары, а второй продлевается за 0, так что
     * разбивка "сколько принёс каждый курс" была бы неточной. Общая сумма
     * не искажается (см. monthlyRevenue()), только её распределение по
     * курсам — так и оставляем нераспределённой.
     */
    private function courseStats()
    {
        return Course::query()
            ->withCount(['students as active_students_count' => function ($q) {
                $q->where('course_user.status', 'active');
            }])
            ->get()
            ->map(fn (Course $course) => [
                'title' => $course->title,
                'students' => $course->active_students_count,
            ])
            ->sortByDesc('students')
            ->values();
    }

    /**
     * Один общий доход за текущий календарный месяц, без разбивки по
     * курсам — см. courseStats().
     */
    private function monthlyRevenue(): int
    {
        return (int) Payment::query()
            ->where('status', 'succeeded')
            ->where('is_promise', false)
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount_cents');
    }
}
