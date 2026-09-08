<?php

namespace App\Http\Controllers\Admin\Crm\Reminder;

use App\Http\Controllers\Controller;
use App\Models\CrmReminder;

class DestroyController extends Controller
{
    public function __invoke(CrmReminder $reminder)
    {
        $reminder->delete();

        return response()->json(['ok' => true]);
    }
}
