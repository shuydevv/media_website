<?php

namespace App\Http\Requests\Admin\Lesson;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meet_link' => ['nullable', 'string'],
            'recording_link' => ['nullable', 'string'],
            'chat_shift_seconds' => ['nullable', 'integer', 'between:-3600,3600'],
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
            'title' => 'Тема',
            'description' => 'Описание',
            'meet_link' => 'Ссылка на трансляцию',
            'recording_link' => 'Ссылка на запись',
            'chat_shift_seconds' => 'Сдвиг чата',
            'notes_link' => 'Ссылка на конспект',
            'homework_id' => 'Домашнее задание',
            'image' => 'Изображение',
        ];
    }
}
