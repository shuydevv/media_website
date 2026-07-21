<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\BillingIntervalRequest;
use App\Http\Requests\Student\BillingPromoRequest;
use App\Models\Course;
use App\Models\Payment;
use App\Service\BillingService;

class BillingSettingsController extends Controller
{
    public function show(BillingService $billing)
    {
        $user = auth()->user();

        $enrollments = $user->courses()
            ->wherePivot('status', 'active')
            ->get()
            ->map(fn ($course) => [
                'course' => $course,
                'billingEnabled' => $billing->isBillingEnabled($user, $course),
                'intervalDays' => $billing->intervalDays($user, $course),
                'nextDueAt' => $billing->nextDueDate($user, $course),
                'promoCode' => $billing->attachedPromoCode($user, $course),
                'priceCents' => $billing->priceForEnrollment($user, $course),
            ]);

        // История платежей — по всем курсам сразу (в т.ч. по уже неактивным
        // записям), не только по текущим активным enrollments выше.
        $payments = Payment::where('user_id', $user->id)
            ->with('course')
            ->orderByDesc('created_at')
            ->get();

        return view('student.billing.show', compact('enrollments', 'payments'));
    }

    public function update(BillingIntervalRequest $request, Course $course, BillingService $billing)
    {
        $billing->setBillingInterval(auth()->user(), $course, (int) $request->validated('interval_days'));

        return redirect()->route('student.billing.show')
            ->with('success', 'Периодичность оплаты обновлена. Изменение вступит в силу со следующего платежа.');
    }

    public function applyPromo(BillingPromoRequest $request, Course $course, BillingService $billing)
    {
        try {
            $promo = $billing->applyPromoCode(auth()->user(), $course, $request->validated('code'));
        } catch (\DomainException $e) {
            return back()->withErrors(['code' => $e->getMessage()])->withInput();
        }

        return redirect()->route('student.billing.show')
            ->with('success', "Промокод «{$promo->code}» применён.");
    }
}
