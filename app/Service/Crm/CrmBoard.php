<?php

namespace App\Service\Crm;

use App\Models\User;
use App\Models\UserActivityDay;
use App\Support\CrmCourseState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Общая выборка и расчёты CRM — один источник для /admin/crm, главной
 * админки и утренней сводки в Telegram (crm:morning-digest), чтобы цифры
 * не разъезжались.
 */
class CrmBoard
{
    /**
     * Ученики «в работе»: без демо, фейков (crm_excluded), неподтверждённых
     * регистраций и архива (completed/lost). Статус считается в памяти —
     * см. комментарий в Admin\Crm\IndexController.
     */
    public function workingSet(array $with = []): Collection
    {
        $students = User::query()
            ->where('role', User::ROLE_READER)
            ->visibleInCrm()
            ->with(array_merge([
                'courses',
                'payments' => fn ($q) => $q->orderByDesc('paid_at'),
                'crmReminders',
            ], $with))
            ->orderByDesc('created_at')
            ->get()
            ->reject(fn (User $u) => $u->isClosedInCrm())
            ->values();

        $this->attachLastActivity($students);

        return $students;
    }

    /** Последний день на платформе — одним запросом на весь список. */
    public function attachLastActivity(Collection $students): void
    {
        if ($students->isEmpty()) {
            return;
        }

        $last = UserActivityDay::query()
            ->whereIn('user_id', $students->pluck('id'))
            ->selectRaw('user_id, MAX(date) as last_date')
            ->groupBy('user_id')
            ->pluck('last_date', 'user_id');

        foreach ($students as $student) {
            $date = $last->get($student->id);
            $student->crmLastActiveDate = $date ? Carbon::parse($date)->startOfDay() : null;
        }
    }

    /**
     * Сколько ещё может прийти до конца месяца (с автоплатежом и без —
     * деньги те же). Берём курсы с регулярной оплатой, у которых
     * дата «до» в этом месяце (или просрочена не больше 30 дней — ещё можно
     * вернуть), сумма — прошлая настоящая оплата по курсу (0 ₽ у второго
     * курса пакета так и останется нулём), нет оплат — цена курса.
     *
     * @return array{rub: int, students: int, overdue: int, rows: Collection}
     */
    public function forecast(Collection $students): array
    {
        $from = now()->startOfDay()->subDays(30);
        $to = now()->endOfMonth();

        $rows = collect();
        foreach ($students as $student) {
            foreach ($student->courses->whereIn('pivot.status', ['active', 'suspended']) as $course) {
                if ($course->pivot->billing_interval_days === null) {
                    continue;
                }
                $s = CrmCourseState::for($student, $course);
                if (! $s['until'] || $s['until']->lt($from) || $s['until']->gt($to)) {
                    continue;
                }
                $rows->push([
                    'student' => $student,
                    'course' => $course,
                    'until' => $s['until'],
                    'rub' => $s['defaultAmountRub'],
                    'overdue' => $s['isOverdue'],
                ]);
            }
        }

        return [
            'rub' => (int) $rows->sum('rub'),
            'students' => $rows->pluck('student.id')->unique()->count(),
            'overdue' => $rows->where('overdue', true)->pluck('student.id')->unique()->count(),
            'rows' => $rows->sortBy('until')->values(),
        ];
    }
}
