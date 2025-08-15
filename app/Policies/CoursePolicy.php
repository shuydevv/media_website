<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    // Студент может видеть курс, если есть активная запись
public function view(User $user, Course $course): bool
{
    // Admin и Mentor имеют доступ всегда
    if (in_array($user->role, ['Admin', 'Mentor'])) {
        return true;
    }

    // Для студента — проверяем, что он записан на курс и доступ активен
    $record = $course->users()
        ->where('users.id', $user->id)
        ->first()?->pivot; // поля: is_active, access_starts_at, access_expires_at

    if (!$record || !$record->is_active) {
        return false;
    }

    $now = now();
    if ($record->access_starts_at && $now->lt($record->access_starts_at)) {
        return false; // доступ ещё не начался
    }
    if ($record->access_expires_at && $now->gte($record->access_expires_at)) {
        return false; // доступ закончился
    }

    return true;
}


}
