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
            $existing = CourseUser::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->first();

            // Для уже существующего зачисления enrolled_at/source/promo_code —
            // история, а не текущее состояние: их нельзя молча затирать значениями
            // по умолчанию при каждом повторном вызове (например, при ручной
            // реактивации доступа админом после истечения промокода) — иначе
            // Homework::isLessonBeforeEnrollment() решит, что все домашки до
            // сегодняшнего дня "от прошлого потока", и спрячет их вместе с уже
            // сделанными. Явно переданные в $meta значения всё ещё побеждают.
            $payload = [
                'status'      => $meta['status']      ?? 'active',
                'enrolled_at' => $meta['enrolled_at'] ?? $existing?->enrolled_at ?? now(),
                'expires_at'  => $meta['expires_at']  ?? null,
                'source'      => $meta['source']      ?? $existing?->source     ?? null,
                'payment_id'  => $meta['payment_id']  ?? $existing?->payment_id ?? null,
                'promo_code'  => $meta['promo_code']  ?? $existing?->promo_code ?? null,
            ];

            $user->courses()->syncWithoutDetaching([
                $course->id => $payload
            ]);

            if (!$existing) {
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
