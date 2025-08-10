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
    $v = $request->validated();

    $start = \Illuminate\Support\Carbon::createFromFormat('H:i', $v['start_time']);
    $end   = $start->copy()->addMinutes($v['duration_minutes']);

    CourseSession::create([
        'course_id'         => $v['course_id'],
        'date'              => $v['date'],
        'start_time'        => $start->format('H:i:s'),
        'end_time'          => $end->format('H:i:s'),
        'duration_minutes'  => $v['duration_minutes'],   // ← добавь это
        'status'            => $v['status'],
    ]);

    return redirect()->route('admin.sessions.index')->with('success', 'Занятие успешно создано');
}

}
