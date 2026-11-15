<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseUser;
use App\Models\User;
use App\Support\CrmCourseState;
use Illuminate\Http\Request;

/**
 * Галочка «Напомнил» в строке курса: пометка только для админа, ученику
 * ничего не отправляет. Снимается сама при оплате (BillingService::recordPayment(),
 * AccessController). Хранится не в reminder_sent_at — тот флаг принадлежит
 * автоматическому напоминанию (SendBillingReminders).
 */
class RemindedController extends Controller
{
    public function __invoke(Request $request, User $user, Course $course)
    {
        $data = $request->validate(['reminded' => ['required', 'boolean']]);

        $pivot = CourseUser::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $pivot->admin_reminded_at = $data['reminded'] ? now() : null;
        $pivot->admin_reminded_by = $data['reminded'] ? auth()->id() : null;
        $pivot->save();

        return response()->json(CrmCourseState::response($user, $course));
    }
}
