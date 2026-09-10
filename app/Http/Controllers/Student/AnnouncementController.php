<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementDismissal;

class AnnouncementController extends Controller
{
    public function dismiss(Announcement $announcement)
    {
        AnnouncementDismissal::firstOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => auth()->id()],
            ['dismissed_at' => now()]
        );

        // htmx игнорирует свап на 204 (No Content) — нужен именно 200 с пустым телом,
        // чтобы hx-swap="outerHTML" реально убрал баннер из DOM.
        return response('', 200);
    }
}
