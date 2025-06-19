<?php

namespace App\Http\Controllers\Admin\Session;

use App\Http\Controllers\Controller;
use App\Models\CourseSession;

class EditController extends Controller
{
    public function __invoke(CourseSession $session)
    {
        return view('admin.sessions.edit', compact('session'));
    }
}
