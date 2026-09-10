<?php

namespace App\Http\Controllers\Admin\Announcement;

use App\Http\Controllers\Controller;
use App\Models\Announcement;

class DeactivateController extends Controller
{
    public function __invoke(Announcement $announcement)
    {
        $announcement->update(['is_active' => false]);

        return redirect()->route('admin.announcements.index');
    }
}
