<?php

namespace App\Http\Controllers\Admin\Crm\Reminder;

use App\Http\Controllers\Controller;
use App\Models\CrmReminder;
use App\Models\User;
use App\Support\CrmDate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StoreController extends Controller
{
    public function __invoke(Request $request, User $user)
    {
        $data = $request->validate([
            'due_at' => ['required', 'date'],
            'note' => ['required', 'string', 'max:500'],
        ]);

        $reminder = CrmReminder::create([
            'user_id' => $user->id,
            'created_by_user_id' => auth()->id(),
            'due_at' => Carbon::parse($data['due_at']),
            'note' => $data['note'],
        ]);

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
