<?php

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Carbon;

class SubmissionPolicy
{
    public function view(User $user, Submission $submission): bool
    {
        // Админ — всегда можно
        if ($user->isAdmin()) {
            return true;
        }

        // Куратор — можно, если лок свободен/его/истёк
        if ($user->isMentor()) {
            $lockedBy = $submission->locked_by;
            $expires  = $submission->lock_expires_at ? Carbon::parse($submission->lock_expires_at) : null;

            if (empty($lockedBy)) return true;                             // никто не держит
            if ($lockedBy === $user->id) return true;                      // держит сам
            if ($expires && $expires->isPast()) return true;               // лок истёк

            return false; // кто-то другой держит активный лок
        }

        // Студент — только свою работу
        return $submission->user_id === $user->id;
    }

    public function update(User $user, Submission $submission): bool
    {
        // Админ — всегда можно
        if ($user->isAdmin()) {
            return true;
        }

        // Куратор — редактировать можно при тех же условиях,
        // а сам "перелок" сделаете в контроллере при открытии
        if ($user->isMentor()) {
            $lockedBy = $submission->locked_by;
            $expires  = $submission->lock_expires_at ? Carbon::parse($submission->lock_expires_at) : null;

            return empty($lockedBy)
                || $lockedBy === $user->id
                || ($expires && $expires->isPast());
        }

        return false;
    }
}
