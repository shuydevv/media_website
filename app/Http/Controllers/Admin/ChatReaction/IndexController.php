<?php

namespace App\Http\Controllers\Admin\ChatReaction;

use App\Http\Controllers\Controller;
use App\Models\ChatReactionType;

class IndexController extends Controller
{
    public function __invoke()
    {
        return view('admin.chat-reactions.index', [
            'reactions' => ChatReactionType::orderBy('sort')->orderBy('id')->get(),
        ]);
    }
}
