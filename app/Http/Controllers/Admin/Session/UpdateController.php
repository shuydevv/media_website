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
    $v = $request->validated();

    // Пересчёт времени окончания от стартового + длительность
    $start = Carbon::createFromFormat('H:i', $v['start_time']);
    $end   = $start->copy()->addMinutes($v['duration_minutes']);

    $session->date             = $v['date'];                       // ← теперь можно менять дату
    $session->start_time       = $start->format('H:i:s');
    $session->end_time         = $end->format('H:i:s');
    $session->duration_minutes = $v['duration_minutes'];           // ← сохраняем длительность
    $session->status           = $v['status'];
    $session->save();

    return redirect()
        ->route('admin.sessions.index')
        ->with('success', 'Занятие успешно обновлено');
}

}
