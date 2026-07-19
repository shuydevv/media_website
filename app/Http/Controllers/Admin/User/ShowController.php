<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Service\BillingService;

class ShowController extends Controller
{
    public function __invoke(User $user, BillingService $billing) {
        $enrollments = $user->courses()->wherePivot('status', 'active')->get();
        $payments = Payment::where('user_id', $user->id)->with('course')->latest()->limit(20)->get();

        return view('admin.users.show', compact('user', 'enrollments', 'payments', 'billing'));
    }
}
