<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromoAttachRequest;
use App\Models\Course;
use App\Models\User;
use App\Service\BillingService;

class PromoAttachmentController extends Controller
{
    public function store(PromoAttachRequest $request, User $user, Course $course, BillingService $billing)
    {
        try {
            $promo = $billing->applyPromoCode($user, $course, $request->validated('code'));
        } catch (\DomainException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return redirect()->route('admin.user.show', $user)
            ->with('success', "Промокод «{$promo->code}» подключён к «{$course->title}».");
    }

    public function destroy(User $user, Course $course, BillingService $billing)
    {
        $billing->removePromoCode($user, $course);

        return redirect()->route('admin.user.show', $user)
            ->with('success', "Промокод отключён от «{$course->title}».");
    }
}
