<?php

namespace App\Http\Controllers\Admin\Homework;

use App\Http\Controllers\Controller;
use App\Models\Homework;

class DestroyController extends Controller
{
    public function __invoke(Homework $homework)
    {
        // Удалим связанные задачи
        $homework->tasks()->delete();

        // Удалим саму домашку
        $homework->delete();

        return redirect()->route('admin.homeworks.index')
            ->with('success', 'Домашнее задание удалено');
    }
}
