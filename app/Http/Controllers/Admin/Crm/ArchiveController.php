<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ArchiveController extends Controller
{
    /**
     * "Завершил курс" и "Отказался" — те же, кого IndexController исключает
     * из основного списка (см. её комментарий про isClosedInCrm()), только
     * тут наоборот оставляем именно их.
     */
    public function __invoke(Request $request, \App\Service\Crm\CrmBoard $board)
    {
        $q = trim($request->string('q')->toString());

        $all = User::query()
            ->where('role', User::ROLE_READER)
            ->visibleInCrm()
            ->with([
                'courses',
                'payments' => fn ($query) => $query->orderByDesc('paid_at'),
                'crmReminders',
                'crmEvents.course',
                'crmEvents.creator',
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
            ->filter(fn (User $u) => $u->isClosedInCrm())
            ->values();

        $board->attachLastActivity($all);

        $perPage = 20;
        $page = (int) $request->input('page', 1);
        $students = new LengthAwarePaginator(
            $all->forPage($page, $perPage)->values(),
            $all->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // «Почему уходят» — по всем отказавшимся (без учёта поиска не
        // считаем: поиск обычно ищет одного человека, сводка тогда пустая).
        $lostReasons = $all
            ->filter(fn (User $u) => $u->crm_stage === 'lost')
            ->countBy(fn (User $u) => $u->crm_lost_reason ?? 'unknown')
            ->sortDesc();

        return view('admin.crm.archive', compact('students', 'q', 'lostReasons'));
    }
}
