<?php

namespace App\Http\Controllers\Admin\ChatReaction;

use App\Http\Controllers\Controller;
use App\Models\ChatReactionType;
use Illuminate\Http\Request;

/** Название, ряд (основной/доп.), вкл/выкл. Удаления нет — только выключение. */
class UpdateController extends Controller
{
    public function __invoke(Request $request, ChatReactionType $reaction)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:64'],
            'placement' => ['sometimes', 'required', 'in:main,extra'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $reaction->update($data);

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('success', 'Сохранено');
    }
}
