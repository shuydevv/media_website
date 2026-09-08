<?php

namespace App\Http\Controllers\Admin\Crm\Reminder;

use App\Http\Controllers\Controller;
use App\Models\CrmReminder;
use App\Support\CrmDate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class UpdateController extends Controller
{
    /**
     * Сбрасываем notified_at при переносе даты вперёд — иначе перенесённое
     * на будущее напоминание, письмо по которому уже ушло на старую дату,
     * молча не пришлёт письмо повторно, когда наступит новая дата (см.
     * NotifyCrmRemindersDue: письмо шлётся один раз, пока notified_at пуст).
     */
    public function __invoke(Request $request, CrmReminder $reminder)
    {
        $data = $request->validate([
            'due_at' => ['required', 'date'],
            'note' => ['required', 'string', 'max:500'],
        ]);

        $dueAt = Carbon::parse($data['due_at']);

        $reminder->due_at = $dueAt;
        $reminder->note = $data['note'];
        if (! $dueAt->isPast()) {
            $reminder->notified_at = null;
        }
        $reminder->save();

        return response()->json([
            'ok' => true,
            'reminder' => [
                'id' => $reminder->id,
                'due_at' => $reminder->due_at->format('Y-m-d'),
                'due_at_label' => CrmDate::format($reminder->due_at),
                'note' => $reminder->note,
                'is_active' => $reminder->isActive(),
            ],
        ]);
    }
}
