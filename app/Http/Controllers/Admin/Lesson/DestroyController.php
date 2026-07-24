<?php

namespace App\Http\Controllers\Admin\Lesson;

use App\Http\Controllers\Controller;
use App\Models\Homework;
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

        // homeworks.lesson_id -> lessons.id задуман как ON DELETE SET NULL
        // (см. миграцию create_homeworks_table), но таблица homeworks —
        // MyISAM, а MyISAM не поддерживает внешние ключи по-настоящему:
        // MySQL принимает синтаксис при создании таблицы, но реального
        // constraint не создаёт и на delete() ничего не делает. Без этой
        // строки домашки остались бы ссылаться на уже несуществующий
        // lesson_id — Homework::isLessonUpcoming() в таком состоянии не
        // может понять, наступил урок или нет, и по умолчанию НЕ прячет
        // домашку (защитное поведение "не можем утверждать — не прячем").
        Homework::where('lesson_id', $lesson->id)->update(['lesson_id' => null]);

        // Удаляем урок
        $lesson->delete();

        return redirect()->route('admin.lessons.index')
            ->with('success', 'Урок успешно удалён');
    }
}
