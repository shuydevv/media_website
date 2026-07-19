<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use App\Service\BillingService;
use Illuminate\Http\Request;

class AutopayController extends Controller
{
    public function update(Request $request, User $user, Course $course, BillingService $billing)
    {
        $billing->setAutopayEnabled($user, $course, $request->boolean('enabled'));

        return redirect()->route('admin.user.show', $user)
            ->with('success', $request->boolean('enabled')
                ? "Автоплатёж включён для «{$course->title}»."
                : "Автоплатёж выключен для «{$course->title}».");
    }
}
