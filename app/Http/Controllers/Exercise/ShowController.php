<?php

namespace App\Http\Controllers\Exercise;

use App\Http\Controllers\Controller;
use App\Models\Task;

class ShowController extends Controller
{
    public function __invoke(Task $exercise)
    {
        abort_unless($exercise->is_public && $exercise->type, 404);

        return view('exercise.show', ['task' => $exercise]);
    }
}
