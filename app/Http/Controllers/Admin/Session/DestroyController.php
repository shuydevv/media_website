<?php

namespace App\Http\Controllers\Admin\Session;

use App\Http\Controllers\Controller;
use App\Models\CourseSession;

class DestroyController extends Controller
{
    public function __invoke(CourseSession $session)
    {
        // У сессии может быть привязан урок (course_session_id ON DELETE CASCADE
        // в миграции lessons) — удаление сессии молча удалит и сам урок со всем
        // его содержимым (запись, конспект и т.д.). Такой снос по одной кнопке
        // "удалить сессию" — неожиданный побочный эффект, поэтому блокируем.
        if ($session->lesson) {
            return back()->with(
                'error',
                'Нельзя удалить сессию, к которой привязан урок — сначала удалите или отвяжите урок «' . $session->lesson->title . '».'
            );
        }

        $session->delete();

        return redirect()
            ->route('admin.sessions.index')
            ->with('success', 'Занятие успешно удалено');
    }
}
