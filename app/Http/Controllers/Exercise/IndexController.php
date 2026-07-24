<?php

namespace App\Http\Controllers\Exercise;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Task;
use Illuminate\Http\Request;

/**
 * Публичная (SEO) страница банка заданий — та же сущность, что и в личном
 * кабинете/домашках (App\Models\Task), просто отфильтрована по is_public и
 * рендерится без формы/авторизации: обычная страница + статичный "Показать
 * ответ" (см. exercise/show.blade.php), а не интерактивная проверка.
 */
class IndexController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = Task::query()->where('is_public', true)->whereNotNull('type')->with('category');

        if ($categoryId = $request->query('category_id')) {
            $q->where('category_id', (int) $categoryId);
        }

        $tasks = $q->orderByDesc('id')->paginate(20)->withQueryString();
        $categories = Category::orderBy('title')->get(['id', 'title']);

        return view('exercise.index', [
            'tasks' => $tasks,
            'categories' => $categories,
        ]);
    }
}
