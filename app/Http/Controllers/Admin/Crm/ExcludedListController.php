<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ExcludedListController extends Controller
{
    /**
     * Вкладка «Не учитываются» — свои/тестовые аккаунты (crm_excluded). Те же
     * карточки, что в «В работе»: дату доступа и оплату можно менять как
     * обычно, просто эти ученики не идут в статусы, счётчики и доход.
     */
    public function __invoke(Request $request, \App\Service\Crm\CrmBoard $board)
    {
        $q = trim($request->string('q')->toString());

        $all = User::query()
            ->where('role', User::ROLE_READER)
            ->excludedFromCrm()
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
            ->get();

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

        return view('admin.crm.archive', [
            'students' => $students,
            'q' => $q,
            'tab' => 'excluded',
        ]);
    }
}
