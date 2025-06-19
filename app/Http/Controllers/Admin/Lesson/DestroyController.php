<?php

namespace App\Http\Controllers\Admin\Lesson;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Support\Facades\Storage;

class DestroyController extends Controller
{
    public function __invoke(Lesson $lesson)
    {
        // Удаляем изображение, если оно есть
        if ($lesson->image && Storage::disk('public')->exists($lesson->image)) {
            Storage::disk('public')->delete($lesson->image);
        }

        // Удаляем урок
        $lesson->delete();

        return redirect()->route('admin.lessons.index')
            ->with('success', 'Урок успешно удалён');
    }
}
