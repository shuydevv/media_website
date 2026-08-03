<?php

namespace App\Http\Requests\Admin\Lesson;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // unique — без него CourseSession::lesson() (hasOne) молча
            // прячет второй урок на то же занятие (двойной сабмит формы,
            // повторный выбор занятия): он существует в БД, но не
            // показывается ни на странице курса, ни на дашборде.
            'course_session_id' => ['required', 'exists:course_sessions,id', Rule::unique('lessons', 'course_session_id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meet_link' => ['nullable', 'string'],
            'recording_link' => ['nullable', 'string'],
            'short_class' => ['nullable','string','max:255'],
            'notes_link' => ['nullable', 'url'],
            'homework_id' => ['nullable', 'exists:homeworks,id'],
            'image' => ['nullable', 'image', 'max:5120'], // до 5MB
            'lesson_type'       => ['required','in:theory,practice'],
        ];
    }

    public function attributes(): array
    {
        return [
            'course_session_id' => 'Занятие',
            'title' => 'Тема',
            'description' => 'Описание',
            'meet_link' => 'Ссылка на трансляцию',
            'recording_link' => 'Ссылка на запись',
            'notes_link' => 'Ссылка на конспект',
            'homework_id' => 'Домашнее задание',
            'image' => 'Изображение',
        ];
    }
}
