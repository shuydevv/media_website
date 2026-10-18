<?php

namespace App\Http\Controllers\Admin\Terms\Photos;

use App\Http\Controllers\Controller;
use App\Models\Term;

/**
 * Фото терминов тренажёра: у кого есть, у кого нет, и загрузка новых.
 * Сама загрузка — StoreController, по одному файлу за запрос (см. view).
 */
class IndexController extends Controller
{
    public function __invoke()
    {
        $terms = Term::active()->orderBy('sort_order')->get(['id', 'title', 'qualifier', 'image', 'image_path', 'priority']);

        return view('admin.terms.photos', [
            'terms' => $terms,
            'missing' => $terms->whereNull('image_path')->values(),
        ]);
    }
}
