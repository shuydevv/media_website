<?php

namespace App\Http\Controllers\Admin\Homework;

use App\Http\Controllers\Controller;
use App\Models\Homework;
use App\Models\HomeworkTask;

class EditController extends Controller
{
    public function __invoke(Homework $homework)
    {
        $homework->load('tasks'); // Загружаем связанные задания

        return view('admin.homeworks.edit', compact('homework'));
    }
}
