<?php

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    // Студент видит/создаёт/редактирует ТОЛЬКО свою работу
    public function view(User $user, Submission $submission): bool
    {
        if (in_array($user->role, ['Admin','Mentor'])) return true;
        return $submission->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->role === 'Student';
    }

    public function update(User $user, Submission $submission): bool
    {
        // Ментор и админ могут проверять; студент — не может изменять после сдачи
        if (in_array($user->role, ['Admin','Mentor'])) return true;
        return false;
    }
}
