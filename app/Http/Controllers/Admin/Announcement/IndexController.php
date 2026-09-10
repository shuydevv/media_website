<?php

namespace App\Http\Controllers\Admin\Announcement;

use App\Http\Controllers\Controller;
use App\Models\Announcement;

class IndexController extends Controller
{
    public function __invoke()
    {
        $announcements = Announcement::with('courses')->latest()->get();

        return view('admin.announcements.index', compact('announcements'));
    }
}
