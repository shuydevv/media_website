<?php

namespace App\Service;

use App\Models\Course;
use App\Models\CourseUser;
use App\Models\User;
use App\Notifications\EnrolledInCourseNotification;
use Illuminate\Support\Facades\DB;

class EnrollmentService
{
    public function enrollUser(User $user, Course $course, array $meta = []): void
    {
        DB::transaction(function () use ($user, $course, $meta) {
            $isNewEnrollment = !CourseUser::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->exists();

            $payload = [
                'status'      => $meta['status']      ?? 'active',
                'enrolled_at' => $meta['enrolled_at'] ?? now(),
                'expires_at'  => $meta['expires_at']  ?? null,
                'source'      => $meta['source']      ?? null,
                'payment_id'  => $meta['payment_id']  ?? null,
                'promo_code'  => $meta['promo_code']  ?? null,
            ];

            $user->courses()->syncWithoutDetaching([
                $course->id => $payload
            ]);

            if ($isNewEnrollment) {
                $user->notify(new EnrolledInCourseNotification($course));
            }
        });
    }

    public function suspend(User $user, Course $course): void
    {
        $user->courses()->updateExistingPivot($course->id, ['status' => 'suspended']);
    }

    public function complete(User $user, Course $course): void
    {
        $user->courses()->updateExistingPivot($course->id, ['status' => 'completed']);
    }
}
