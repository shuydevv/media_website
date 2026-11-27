<?php

namespace App\Http\Controllers\Admin\ChatReaction;

use App\Http\Controllers\Controller;
use App\Models\ChatReactionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Порядок реакций после перетаскивания: ids — в новом порядке. */
class SortController extends Controller
{
    public function __invoke(Request $request)
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $i => $id) {
                ChatReactionType::whereKey($id)->update(['sort' => $i + 1]);
            }
        });

        return response()->json(['ok' => true]);
    }
}
