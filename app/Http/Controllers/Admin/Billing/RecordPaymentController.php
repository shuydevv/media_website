<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RecordPaymentRequest;
use App\Models\Course;
use App\Models\User;
use App\Service\BillingService;

class RecordPaymentController extends Controller
{
    public function __invoke(RecordPaymentRequest $request, User $user, Course $course, BillingService $billing)
    {
        $data = $request->validated();

        $billing->recordPayment($user, $course, (int) round($data['amount_rub'] * 100), 'manual', [
            'billing_interval_days' => $data['billing_interval_days'] ?? null,
            'recorded_by_user_id' => auth()->id(),
            'note' => $data['note'] ?? null,
        ]);

        return redirect()->route('admin.user.show', $user)
            ->with('success', "Платёж по «{$course->title}» зафиксирован, доступ продлён.");
    }
}
