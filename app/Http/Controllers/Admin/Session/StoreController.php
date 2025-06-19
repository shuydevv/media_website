<?php

namespace App\Http\Controllers\Admin\Session;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Session\StoreRequest;
use App\Models\CourseSession;

class StoreController extends Controller
{
    public function __invoke(StoreRequest $request)
    {
        $validated = $request->validated();

        CourseSession::create([
            'course_id'   => $validated['course_id'],
            'date'        => $validated['date'],
            'start_time'  => $validated['start_time'],
            'end_time'    => $validated['end_time'],
            'status'      => $validated['status'] ?? 'active',
        ]);

        return redirect()->route('admin.sessions.index')
            ->with('success', 'Занятие успешно создано.');
    }
}
