<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Models\User;

class BotsPreviewController extends Controller
{
    public function __invoke()
    {
        $totalCount = User::botCandidates()->count();

        $candidates = User::botCandidates()
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        return view('admin.users.bots-preview', compact('candidates', 'totalCount'));
    }
}
