<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\UpdateRequest;
use App\Models\Course;
use App\Models\User;
use App\Service\EnrollmentService;
use Illuminate\Support\Carbon;

class UpdateController extends Controller
{
    public function __invoke(UpdateRequest $request, User $user, EnrollmentService $enroll) {
        $data = $request->validated();

        $courseIds = $data['course_ids'] ?? [];
        $accessUntil = $data['access_until'] ?? [];
        unset($data['course_ids'], $data['access_until']);

        $data['name'] = $data['name'] ?: trim($data['first_name'].' '.($data['last_name'] ?? ''));

        $user->update($data);

        // Снятая галочка не присылает id курса вовсе, поэтому активные
        // зачисления, которых нет среди $courseIds, нужно закрыть явно —
        // иначе course_user так и останется status=active навсегда.
        // Закрываем как completed, а не suspended: suspended — это «не
        // оплатил» (enrollments:expire), такой курс CRM и утренняя сводка
        // показывают как просроченный. Админ курс убрал сам — напоминать
        // о нём не нужно (часто человек просто перешёл на другой курс).
        // Только active: приостановленные в форме без галочки (EditController),
        // их трогать нельзя — иначе любая правка профиля молча убрала бы
        // неоплаченный курс из CRM. Их убирают кнопкой в CRM (RemoveCourseController).
        $currentActiveCourseIds = $user->courses()
            ->wherePivot('status', 'active')
            ->pluck('courses.id')
            ->all();

        foreach (array_diff($currentActiveCourseIds, $courseIds) as $courseId) {
            $enroll->complete($user, Course::findOrFail($courseId));
        }

        // enrollUser() — upsert по (user_id, course_id): для уже выданного
        // курса просто обновит дату, для нового — создаст зачисление.
        foreach ($courseIds as $courseId) {
            $course = Course::findOrFail($courseId);
            $enroll->enrollUser($user, $course, [
                'source' => 'manual',
                'expires_at' => Carbon::parse($accessUntil[$courseId])->endOfDay(),
            ]);
        }

        return redirect()->route('admin.user.show', $user)->with('success', 'Изменения сохранены.');
    }
}
