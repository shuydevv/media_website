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
            'meet_link' => ['nullable', 'url'],
            'recording_link' => ['nullable', 'url'],
            'notes_link' => ['nullable', 'url'],
            'homework_id' => ['nullable', 'exists:homeworks,id'],
            'image' => ['nullable', 'image', 'max:5120'], // до 5MB
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'Тема',
            'meet_link' => 'Ссылка на трансляцию',
            'recording_link' => 'Ссылка на запись',
            'notes_link' => 'Ссылка на конспект',
            'homework_id' => 'Домашнее задание',
            'image' => 'Изображение',
        ];
    }
}
