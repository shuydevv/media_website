<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;

class CreateController extends Controller
{
    public function __invoke() {
        $roles = User::getRoles();
        $courses = Course::orderBy('title')->get(['id', 'title']);
        return view('admin.users.create', compact('roles', 'courses'));
    }
}
