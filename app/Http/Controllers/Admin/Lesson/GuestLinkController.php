<?php

namespace App\Http\Controllers\Admin\Lesson;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonChat;
use Illuminate\Support\Str;

/** Создать или сменить гостевую ссылку на эфир урока (/live/<код>). */
class GuestLinkController extends Controller
{
    public function __invoke(Lesson $lesson)
    {
        LessonChat::updateOrCreate(['lesson_id' => $lesson->id], ['guest_token' => Str::random(24)]);

        return back()->with('success', 'Гостевая ссылка готова');
    }
}
