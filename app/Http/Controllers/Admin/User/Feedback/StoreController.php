<?php

namespace App\Http\Controllers\Admin\User\Feedback;

use App\Http\Controllers\Controller;
use App\Models\StudentFeedback;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoreController extends Controller
{
    public function __invoke(Request $request, User $user)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(StudentFeedback::KINDS))],
            'note' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'note' => 'О чём',
        ]);

        StudentFeedback::create([
            'user_id' => $user->id,
            'author_id' => $request->user()->id,
            'kind' => $data['kind'],
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('success', 'Запись добавлена в журнал.');
    }
}
