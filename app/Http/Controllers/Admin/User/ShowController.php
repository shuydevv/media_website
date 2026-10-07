<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Service\StudentReport;

class ShowController extends Controller
{
    /**
     * Страница ученика — отчёт о прогрессе для преподавателя (см.
     * StudentReport). Оплаты и доступ здесь больше не редактируются: это
     * делается в /admin/crm (Admin\Crm\AccessController), блок "Оплата
     * курсов" дублировал его вторым, расходящимся способом записать платёж.
     */
    public function __invoke(User $user, StudentReport $report)
    {
        return view('admin.users.show', [
            'user' => $user,
            'report' => $report->build($user),
        ]);
    }
}
