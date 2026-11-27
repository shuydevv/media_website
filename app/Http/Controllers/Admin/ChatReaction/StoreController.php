<?php

namespace App\Http\Controllers\Admin\ChatReaction;

use App\Http\Controllers\Controller;
use App\Models\ChatReactionType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Своя реакция чата. Формат — по файлу: webp/gif (анимация), png (статичная,
 * сайт сам добавит «прыжок»), json — Lottie (проверяем, что это похоже на
 * Lottie: есть слои и кадры, иначе плеер молча ничего не покажет).
 */
class StoreController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'placement' => ['required', 'in:main,extra'],
            'file' => ['required', 'file', 'max:1024'],
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $format = match ($ext) {
            'webp' => 'webp',
            'gif' => 'gif',
            'png' => 'png',
            'json' => 'lottie',
            default => null,
        };
        if (! $format) {
            throw ValidationException::withMessages(['file' => 'Нужен файл WebP, GIF, PNG или Lottie (.json)']);
        }
        if ($format === 'lottie') {
            $json = json_decode((string) file_get_contents($file->getRealPath()), true);
            if (! is_array($json) || ! isset($json['layers'], $json['op'])) {
                throw ValidationException::withMessages(['file' => 'Файл .json не похож на анимацию Lottie']);
            }
        } elseif (! @getimagesize($file->getRealPath())) {
            throw ValidationException::withMessages(['file' => 'Файл не открывается как картинка']);
        }

        $path = $file->storeAs('chat-reactions', Str::random(16).'.'.$ext, 'public');

        ChatReactionType::create([
            'name' => $data['name'],
            'format' => $format,
            'file_path' => $path,
            'placement' => $data['placement'],
            'sort' => (int) ChatReactionType::max('sort') + 1,
            'is_active' => true,
        ]);

        return back()->with('success', 'Реакция добавлена');
    }
}
