<?php

namespace App\Http\Controllers\Admin\Main;

use App\Http\Controllers\Controller;
use App\Models\CourseSession;
use App\Models\CrmReminder;
use App\Models\Payment;
use App\Models\Submission;
use App\Models\User;

class IndexController extends Controller
{
    /**
     * Главная админки — «что требует внимания сегодня»: наступившие
     * напоминания CRM, просрочки и истекающие доступы, очередь работ на
     * проверку, ближайшие занятия. Сами списки живут в своих разделах, здесь
     * только сводка со ссылками туда.
     */
    public function __invoke()
    {
        // Та же база и те же правила, что у /admin/crm (Crm\IndexController):
        // статус считает User::crmStatus(), в память грузятся десятки-сотни
        // учеников одной школы — отдельного SQL под дашборд не заводим, чтобы
        // цифры здесь не разъехались с цифрами в CRM.
        $students = User::query()
            ->where('role', User::ROLE_READER)
            ->visibleInCrm()
            ->with(['courses', 'crmReminders'])
            ->get()
            ->reject(fn (User $u) => $u->isClosedInCrm())
            ->values();

        $statusCounts = $students->countBy(fn (User $u) => $u->crmStatus()['key']);

        $dueReminders = $students
            ->flatMap(fn (User $u) => $u->crmReminders
                ->filter(fn (CrmReminder $r) => $r->isActive())
                ->map(fn (CrmReminder $r) => ['reminder' => $r, 'student' => $u]))
            ->sortBy(fn (array $row) => $row['reminder']->due_at)
            ->values();

        $sessions = CourseSession::query()
            ->with(['course', 'lesson'])
            ->where('status', 'active')
            ->whereDate('date', '>=', now()->toDateString())
            ->whereDate('date', '<=', now()->addDays(7)->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit(6)
            ->get();

        return view('admin.main.index', [
            'activeCount' => $statusCounts->get('active', 0),
            'pastDueCount' => $statusCounts->get('past_due', 0),
            'leadCount' => $statusCounts->only(['new', 'contacted', 'trial_done'])->sum(),
            'soonCount' => $students->filter(fn (User $u) => $u->crmExpiresSoon())->count(),
            'dueReminders' => $dueReminders,
            'pendingReviewCount' => Submission::pendingReviewQueue()->count(),
            'monthlyRevenueRub' => $this->monthlyRevenue() / 100,
            'sessions' => $sessions,
        ]);
    }

    /**
     * Доход за текущий календарный месяц — тот же расчёт, что в блоке
     * статистики /admin/crm (Crm\IndexController::monthlyRevenue()).
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
