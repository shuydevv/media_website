<?php

namespace App\Http\Controllers\Admin\Homework\Unlock;

use App\Http\Controllers\Controller;
use App\Models\Homework;
use App\Models\HomeworkUnlock;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Открыть конкретному ученику доступ к этой домашке в обход
 * Homework::isLessonBeforeEnrollment() — см. миграцию create_homework_unlocks_table.
 */
class StoreController extends Controller
{
    public function __invoke(Request $request, Homework $homework)
    {
        $data = $request->validate([
            'user_id' => [
                'required', 'integer',
                Rule::exists('course_user', 'user_id')->where(fn ($q) => $q->where('course_id', $homework->course_id)),
            ],
        ], [
            'user_id.exists' => 'Этот ученик не зачислен на курс этой домашки.',
        ]);

        HomeworkUnlock::firstOrCreate(
            ['homework_id' => $homework->id, 'user_id' => $data['user_id']],
            ['granted_by' => $request->user()->id]
        );

        return back()->with('success', 'Доступ к домашке открыт.');
    }
}
