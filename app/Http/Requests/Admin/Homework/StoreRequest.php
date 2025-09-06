<?php

namespace App\Http\Requests\Admin\Homework;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
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
            'tasks.*.answer' => ['required', 'string'],
            'tasks.*.order' => ['nullable', 'integer'],
            // 'tasks.*.task_number' => ['nullable', 'string'],
            'tasks.*.task_id' => ['required','exists:tasks,id'],
            'tasks.*.max_score' => ['nullable','integer','min:1'],

            // новые текстовые поля
            'tasks.*.passage_text'      => ['nullable', 'string'],   // художественный/публицистический текст
            'tasks.*.left_title'        => ['nullable', 'string'],   // заголовок левой колонки (matching)
            'tasks.*.right_title'       => ['nullable', 'string'],   // заголовок правой колонки (matching)

            // уточнение структуры matching
            // 'tasks.*.matches'           => ['nullable', 'array'],
            'tasks.*.matches.left'      => ['nullable', 'array'],
            'tasks.*.matches.right'     => ['nullable', 'array'],
            'tasks.*.matches.left.*'    => ['nullable', 'string'],
            'tasks.*.matches.right.*'   => ['nullable', 'string'],

            // варианты для image_auto (необязательные)
            'tasks.*.image_auto_options'    => ['nullable', 'array'],
            'tasks.*.image_auto_options.*'  => ['nullable', 'string'],
            'tasks.*.image_auto_strict'     => ['nullable', 'boolean'],

            // порядок важен (если ты ставишь hidden-поле в matching/table)
            'tasks.*.order_matters'     => ['nullable', 'boolean'],

            // загрузка картинки админом (если отправляешь файл)
            'tasks.*.image'             => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            // если иногда приходит уже готовый путь (строкой) — оставь и это правило
            // 'tasks.*.image_path'        => ['nullable', 'string'],

            // уточнения для уже существующих массивов (по желанию, но полезно)
            // 'tasks.*.options'           => ['nullable', 'array'],
            'tasks.*.options.*'         => ['nullable', 'string'],

            'tasks.*.table_content' => ['nullable','string'],

            'due_at'    => ['nullable','date'],


            // 'tasks.*.max_score' => ['nullable','integer','min:1'],

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

            'tasks.*.passage_text'         => 'Текст задания (пассаж)',
            'tasks.*.left_title'           => 'Заголовок левой колонки',
            'tasks.*.right_title'          => 'Заголовок правой колонки',
            'tasks.*.matches.left'         => 'Левая колонка (список)',
            'tasks.*.matches.right'        => 'Правая колонка (список)',
            'tasks.*.image_auto_options'   => 'Варианты ответа (для “Картинка (авто)”)',
            'tasks.*.image_auto_strict'    => 'Порядок важен (для “Картинка (авто)”)',
            'tasks.*.image'                => 'Изображение задания',
            'tasks.*.image_path'           => 'Путь к изображению',
            'tasks.*.options'              => 'Варианты ответа',
            'tasks.*.table_content'        => 'Содержимое таблицы',
        ];
    }
}
