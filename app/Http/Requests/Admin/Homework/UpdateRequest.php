<?php

namespace App\Http\Requests\Admin\Homework;

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
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type'        => ['required', 'string'], // homework или mock

            'tasks' => ['nullable', 'array'],
            'tasks.*.id' => ['nullable', 'integer', 'exists:homework_tasks,id'],
            'tasks.*.type' => ['required', 'string'], // строка, не enum
            'tasks.*.question_text' => ['nullable', 'string'],
            'tasks.*.options' => ['nullable', 'array'],
            'tasks.*.matches' => ['nullable', 'array'],
            'tasks.*.image_path' => ['nullable', 'string'],
            'tasks.*.table' => ['nullable', 'array'],
            'tasks.*.answer' => ['required', 'string'],
            'tasks.*.order' => ['nullable', 'integer'],
            'tasks.*.task_number' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'Название',
            'description' => 'Описание',
            'type' => 'Тип работы',
            'tasks' => 'Задания',
            'tasks.*.type' => 'Тип задания',
            'tasks.*.question_text' => 'Текст задания',
            'tasks.*.answer' => 'Ответ',
        ];
    }
}
