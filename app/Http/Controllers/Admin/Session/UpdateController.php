<?php

namespace App\Http\Controllers\Admin\Session;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Session\UpdateRequest;
use App\Models\CourseSession;
use Illuminate\Support\Carbon;

class UpdateController extends Controller
{
    public function __invoke(UpdateRequest $request, CourseSession $session)
    {
        $validated = $request->validated();

        $startTime = Carbon::createFromFormat('H:i', $validated['start_time']);
        $endTime = $startTime->copy()->addMinutes($validated['duration_minutes']);

        $session->start_time = $startTime->format('H:i:s');
        $session->end_time = $endTime->format('H:i:s');
        $session->status = $validated['status'];
        $session->save();

        return redirect()->route('admin.sessions.index')
            ->with('success', 'Занятие успешно обновлено');
    }
}
