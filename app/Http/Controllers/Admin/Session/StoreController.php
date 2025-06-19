<?php

namespace App\Http\Controllers\Admin\Session;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Session\StoreRequest;
use App\Models\CourseSession;
use Illuminate\Support\Carbon;

class StoreController extends Controller
{
public function __invoke(StoreRequest $request)
{
    $validated = $request->validated();

    // Расчёт времени окончания
    $startTime = Carbon::createFromFormat('H:i', $validated['start_time']);
    $endTime = $startTime->copy()->addMinutes($validated['duration_minutes']);

    CourseSession::create([
        'course_id'     => $validated['course_id'],
        'date'          => $validated['date'],
        'start_time'    => $startTime->format('H:i:s'),
        'end_time'      => $endTime->format('H:i:s'),
        'status'        => $validated['status'],
    ]);

    return redirect()->route('admin.sessions.index')->with('success', 'Занятие успешно создано');
}
}
