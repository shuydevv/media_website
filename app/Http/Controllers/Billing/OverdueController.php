<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Service\BillingService;
use Illuminate\Http\Request;

class OverdueController extends Controller
{
    public function show(Request $request, Course $course, BillingService $billing)
    {
        $user = $request->user();

        if ($billing->hasAccess($user, $course)) {
            return redirect()->route('student.courses.show', $course);
        }

        return view('billing.overdue', [
            'course' => $course,
            'dueAt' => $billing->nextDueDate($user, $course),
            'promiseAvailable' => $billing->isPromiseAvailable($user, $course),
        ]);
    }

    public function promise(Request $request, Course $course, BillingService $billing)
    {
        if (!$billing->isPromiseAvailable($request->user(), $course)) {
            return back()->withErrors(['promise' => 'Обещанный платёж уже использован в этом цикле.']);
        }

        $billing->grantPromisedPayment($request->user(), $course);

        return redirect()->route('student.courses.show', $course)
            ->with('success', 'Доступ продлён на 5 дней. Не забудьте оплатить курс.');
    }
}
