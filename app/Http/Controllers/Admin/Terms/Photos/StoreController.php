<?php

namespace App\Http\Controllers\Admin\Terms\Photos;

use App\Http\Controllers\Controller;
use App\Service\Terms\TermPhotoStore;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Загрузка ОДНОГО фото термина. Страница шлёт выбранные файлы по очереди,
 * каждый отдельным запросом: пачка из ~190 фото по ~300 КБ упёрлась бы в
 * лимиты PHP на размер запроса и число файлов (post_max_size, max_file_uploads).
 *
 * Отвечает JSON — страница пишет по нему строку в журнал загрузки.
 */
class StoreController extends Controller
{
    public function __invoke(Request $request, TermPhotoStore $photos)
    {
        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'photo.mimes' => 'Нужен файл JPG, PNG или WebP.',
            'photo.max' => 'Файл больше 10 МБ.',
        ]);

        $file = $request->file('photo');
        $name = $file->getClientOriginalName();

        $term = $photos->termFor($name);
        if (!$term) {
            return response()->json(['ok' => false, 'file' => $name, 'error' => 'нет термина с таким именем файла'], 422);
        }

        try {
            $photos->store($term, $file->getRealPath());
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'file' => $name, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'file' => $name,
            'term' => $term->title.($term->qualifier ? ' · '.$term->qualifier : ''),
            'url' => asset('storage/'.$term->image_path),
        ]);
    }
}
