<?php

namespace App\Http\Controllers\Admin\Crm\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Service\BillingService;
use App\Support\CrmCourseState;

/**
 * «Отменить» у последнего платежа: удаляет платёж и возвращает запись
 * course_user в состояние до него (BillingService::rollbackPayment()).
 */
class DestroyController extends Controller
{
    public function __invoke(Payment $payment, BillingService $billing)
    {
        $user = $payment->user;
        $course = $payment->course;

        try {
            $billing->rollbackPayment($payment);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(CrmCourseState::response($user, $course));
    }
}
