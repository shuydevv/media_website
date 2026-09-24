<?php

namespace App\Http\Controllers\Admin\Homework\Attempts;

use App\Http\Controllers\Controller;
use App\Models\Homework;
use App\Models\Submission;
use App\Models\User;

/**
 * Обнулить попытки ученика по этой домашке — удаляет все его Submission
 * (включая незавершённую, если есть), чтобы attemptsUsed в
 * Student\SubmissionController::create() снова считался от нуля и ученик
 * мог сдавать домашку заново сверх обычного лимита попыток. Уже начисленный
 * корм (FishFoodService) за прошлые попытки не отзывается — геймификация
 * намеренно не завязана на итоговый результат домашки (см. CLAUDE.md).
 */
class DestroyController extends Controller
{
    public function __invoke(Homework $homework, User $user)
    {
        Submission::where('homework_id', $homework->id)
            ->where('user_id', $user->id)
            ->delete();

        return back()->with('success', "Попытки ученика {$user->name} по этой домашке обнулены.");
    }
}
