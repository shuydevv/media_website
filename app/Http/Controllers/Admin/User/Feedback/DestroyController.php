<?php

namespace App\Http\Controllers\Admin\User\Feedback;

use App\Http\Controllers\Controller;
use App\Models\StudentFeedback;
use App\Models\User;

class DestroyController extends Controller
{
    public function __invoke(User $user, StudentFeedback $feedback)
    {
        abort_unless((int) $feedback->user_id === (int) $user->id, 404);

        $feedback->delete();

        return back()->with('success', 'Запись удалена.');
    }
}
