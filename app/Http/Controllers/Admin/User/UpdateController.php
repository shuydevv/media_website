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
