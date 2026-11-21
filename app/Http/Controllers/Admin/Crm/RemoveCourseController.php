<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseUser;
use App\Models\CrmEvent;
use App\Models\User;
use App\Service\EnrollmentService;

/**
 * Кнопка «Убрать курс» в строке курса CRM: админ сам снял ученика с курса
 * (ушёл, перешёл на другой курс). Запись закрывается как completed — та же,
 * что и снятая галочка в профиле (Admin\User\UpdateController), — и курс
 * пропадает из карточки, утренней сводки и прогноза. Не suspended: так
 * помечается неоплата, и курс висел бы в «Просрочена оплата».
 * Другие курсы ученика не трогаются — статус в CRM считается заново.
 */
class RemoveCourseController extends Controller
{
    public function __invoke(User $user, Course $course, EnrollmentService $enroll)
    {
        CourseUser::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $enroll->complete($user, $course);
        CrmEvent::log($user, 'course_removed', 'Убрал с курса', $course->id);

        $user->load('courses');

        return response()->json([
            'ok' => true,
            'status' => $user->crmStatusPayload(),
        ]);
    }
}
