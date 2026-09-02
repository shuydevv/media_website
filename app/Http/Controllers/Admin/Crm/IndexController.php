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
    public function __invoke(Request $request)
    {
        $q = trim($request->string('q')->toString());

        // isClosedInCrm() (completed/lost) зависит от статусов курсов и
        // ручного crm_stage вперемешку — не сводится к простому WHERE без
        // риска разъехаться с App\Models\User::crmStatus(). CRM у одной
        // школы — это десятки-сотни, не миллионы строк, поэтому фильтруем
        // и пагинируем уже в памяти, одним источником правды на статус.
        $all = User::query()
            ->where('role', User::ROLE_READER)
            ->visibleInCrm()
            ->with([
                'courses',
                // Отсортированы по дате платежа заранее — для каждого курса берём
                // firstWhere('course_id', ...) в шаблоне, без доп. запросов на строку.
                'payments' => fn ($query) => $query->orderByDesc('paid_at'),
            ])
            ->when($q, function ($query) use ($q) {
                $query->where(function ($s) use ($q) {
                    $s->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('created_at')
            ->get()
            ->reject(fn (User $u) => $u->isClosedInCrm())
            ->values();

        // Номер ("снизу вверх") — это позиция по хронологии регистрации,
        // стабильный ярлык "какой ты по счёту", а не позиция на экране —
        // считаем его ДО пересортировки по срочности, иначе номера скакали
        // бы при каждой смене статуса.
        $total = $all->count();
        foreach ($all as $index => $student) {
            $student->crmNumber = $total - $index;
        }

        // Дальше — сортировка по срочности (просрочки/заморозки выше
        // активных), хронологический порядок остаётся только "тай-брейком"
        // внутри одной группы срочности (stable sort — sortBy сохраняет
        // относительный порядок равных элементов).
        $all = $all->sortBy(fn (User $u) => $u->crmSortPriority())->values();

        $perPage = 20;
        $page = (int) $request->input('page', 1);
        $students = new LengthAwarePaginator(
            $all->forPage($page, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.crm.index', [
            'students' => $students,
            'q' => $q,
            'totalUsers' => User::where('role', User::ROLE_READER)->count(),
            'courseStats' => $this->courseStats(),
            'monthlyRevenueRub' => $this->monthlyRevenue() / 100,
        ]);
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
