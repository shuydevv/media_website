<?php

namespace App\Http\Controllers\Admin\Course;

use App\Http\Controllers\Controller;
use App\Models\Course;

class DestroyController extends Controller
{
    public function __invoke(Course $course)
    {
        $course->delete(); // soft delete
        return redirect()
            ->route('admin.courses.index')
            ->with('success', 'Курс успешно удалён.');
    }
}
