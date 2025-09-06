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
        'tasks.*.passage_text'    => ['nullable','string'], // текст (пассаж)
        'tasks.*.options'         => ['nullable'], // у тебя касты в модели — оставляем свободно
        'tasks.*.matches'         => ['nullable'],
        'tasks.*.image'           => ['nullable','image','max:5120'], // <= картинка
        'tasks.*.answer'          => ['required_with:tasks','string'],
        'tasks.*.order'           => ['nullable','integer'],
        'tasks.*.task_number'     => ['nullable','string'],
        'tasks.*.task_id' => ['required','exists:tasks,id'],
        'tasks.*.max_score' => ['required','integer','min:1'],
        'tasks.*.left_title'  => ['nullable','string'],
        'tasks.*.right_title' => ['nullable','string'],

        'tasks.*.image_auto_options'   => ['nullable'],        // строка или массив — нормализуем в контроллере
        'tasks.*.image_auto_options.*' => ['nullable','string'],

        'tasks.*.table_content' => ['nullable','string'],
        'due_at'    => ['nullable','date'],

    ];
}

}
