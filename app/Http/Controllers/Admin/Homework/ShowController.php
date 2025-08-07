<?php

namespace App\Http\Controllers\Admin\Homework;

use App\Http\Controllers\Controller;
use App\Models\Homework;

class ShowController extends Controller
{
    public function __invoke(Homework $homework)
    {
        $homework->load('tasks'); // или tasks, если ты переименуешь

        return view('admin.homeworks.show', compact('homework'));
    }
}
