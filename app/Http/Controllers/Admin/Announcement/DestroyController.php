<?php

namespace App\Http\Controllers\Admin\Announcement;

use App\Http\Controllers\Controller;
use App\Models\Announcement;

class DestroyController extends Controller
{
    public function __invoke(Announcement $announcement)
    {
        $announcement->delete();

        return redirect()->route('admin.announcements.index');
    }
}
