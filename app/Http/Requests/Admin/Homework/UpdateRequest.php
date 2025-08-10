<?php

namespace App\Http\Requests\Admin\Homework;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // добавь свою авторизацию при необходимости
    }

public function rules(): array
{
    return [
        'title'       => ['required','string','max:255'],
        'description' => ['nullable','string'],
        'type'        => ['required','in:homework,mock'],
        'course_id'   => ['required','integer','exists:courses,id'],
        'lesson_id'   => ['required','integer','exists:lessons,id'],

        'tasks'                   => ['sometimes','array'],
        'tasks.*.id'              => ['sometimes','integer'],
        'tasks.*.type'            => ['required_with:tasks','string'],
        'tasks.*.question_text'   => ['nullable','string'],
        'tasks.*.options'         => ['nullable'], // у тебя касты в модели — оставляем свободно
        'tasks.*.matches'         => ['nullable'],
        'tasks.*.table'           => ['nullable'],
        'tasks.*.image'           => ['nullable','image','max:5120'], // <= картинка
        'tasks.*.answer'          => ['required_with:tasks','string'],
        'tasks.*.order'           => ['nullable','integer'],
        'tasks.*.task_number'     => ['nullable','string'],
    ];
}

}
