<?php

namespace App\Http\Requests\Admin\Homework;

use App\Support\TaskContentRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // добавь свою авторизацию при необходимости
    }

    public function rules(): array
    {
        return array_merge(TaskContentRules::rules('homework', 'tasks.*'), [
            'title'       => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'type'        => ['required','in:homework,mock'],
            'due_at'      => ['nullable','date'],
            'mock_number' => ['nullable','integer','min:1'],
            'course_id'   => ['required','integer','exists:courses,id'],
            // К одному уроку — не больше одной домашки любого типа (см.
            // миграцию add_unique_lesson_id_to_homeworks_table). ignore()
            // своей же строки — иначе пересохранение БЕЗ смены урока всегда
            // валилось бы на "занято", ведь строка с этим lesson_id уже есть
            // (это она сама).
            'lesson_id'   => [
                'required', 'integer', 'exists:lessons,id',
                Rule::unique('homeworks', 'lesson_id')->ignore($this->route('homework')),
            ],

            // min:1 — не только UX-подсказка: контроллер синхронизирует задания
            // через whereNotIn('id', $taskIds)->delete(), а пустой массив там
            // компилируется в SQL "1 = 1" (совпадает с любой строкой) — то есть
            // пустой tasks удалил бы ВСЕ задания домашки разом. В форме это уже
            // не нажать (там есть свой JS-guard на "нельзя удалить последнее
            // задание"), но это клиентская подстраховка, а не гарантия —
            // валидация должна отказывать в этом и на сервере.
            'tasks'                   => ['sometimes','array','min:1'],
            'tasks.*.id'              => ['sometimes','integer'],

            // Источник задания: «своё» содержание прямо в домашке или связка
            // с уже существующим заданием банка — см. Admin\Homework\
            // UpdateController::saveTaskLink().
            'tasks.*.source'          => ['required_with:tasks','in:own,bank'],
            'tasks.*.task_id'         => ['required_if:tasks.*.source,bank','nullable','integer','exists:tasks,id'],
            'tasks.*.save_to_bank'    => ['nullable','boolean'],
            'tasks.*.order'           => ['nullable','integer'],
            // Номер в ЕГЭ — только для source=own (см. StoreRequest, тот же
            // комментарий). Раньше здесь валидировалось tasks.*.task_number —
            // это имя нигде не читалось ни контроллером, ни формой (форма
            // всегда слала просто "number"), значение тихо терялось.
            'tasks.*.number'          => ['nullable','string','max:255'],

            // Без mimes-ограничения, в отличие от общего правила — так было
            // изначально в этом запросе, поведение не меняем.
            'tasks.*.image'           => ['nullable','image','max:5120'],
        ]);
    }

    public function messages(): array
    {
        return [
            'lesson_id.unique' => 'К этому уроку уже прикреплена другая домашка — у одного урока может быть только одна.',
        ];
    }
}
