<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function __invoke(Request $request, User $user)
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $user->crm_note = $data['note'] ?? null;
        $user->save();

        return response()->json(['ok' => true]);
    }
}
