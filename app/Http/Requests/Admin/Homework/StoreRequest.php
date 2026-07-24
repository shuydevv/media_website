<?php

namespace App\Http\Requests\Admin\Homework;

use App\Support\TaskContentRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(TaskContentRules::rules('homework', 'tasks.*'), [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type'        => ['required', 'string'], // homework или mock

            'tasks' => ['nullable', 'array'],
            'tasks.*.id' => ['nullable', 'integer', 'exists:homework_tasks,id'],

            // Источник задания: «своё» содержание прямо в домашке или связка
            // с уже существующим заданием банка — см. Admin\Homework\
            // StoreController::saveTaskLink().
            'tasks.*.source' => ['required', 'in:own,bank'],
            'tasks.*.task_id' => ['required_if:tasks.*.source,bank', 'nullable', 'integer', 'exists:tasks,id'],
            'tasks.*.save_to_bank' => ['nullable', 'boolean'],
            'tasks.*.image_path' => ['nullable', 'string'],
            'tasks.*.order' => ['nullable', 'integer'],
            'tasks.*.image_auto_strict' => ['nullable', 'boolean'],

            'due_at'    => ['nullable','date'],
        ]);
    }

    public function attributes(): array
    {
        return array_merge(TaskContentRules::attributes('tasks.*'), [
            'title' => 'Название',
            'description' => 'Описание',
            'type' => 'Тип работы',
            'tasks' => 'Задания',
            'tasks.*.source' => 'Источник задания',
            'tasks.*.task_id' => 'Задание из банка',
            'tasks.*.image_path'           => 'Путь к изображению',
            'tasks.*.image_auto_strict'    => 'Порядок важен (для “Картинка (авто)”)',
        ]);
    }
}
