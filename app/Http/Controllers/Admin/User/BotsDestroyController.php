<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class BotsDestroyController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer'],
        ]);

        // Не доверяем списку ID из формы как есть — пересекаем его с тем же
        // критерием бота ещё раз на сервере, чтобы правка запроса в devtools
        // не могла задеть реального ученика/лида, отобранного между показом
        // превью и сабмитом.
        $deletable = User::botCandidates()
            ->whereIn('id', $data['user_ids'])
            ->get();

        $count = $deletable->count();
        foreach ($deletable as $user) {
            $user->delete();
        }

        return redirect()->route('admin.user.index')
            ->with('success', "Удалено ботов: {$count}");
    }
}
