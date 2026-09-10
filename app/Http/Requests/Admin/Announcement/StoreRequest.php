<?php

namespace App\Http\Requests\Admin\Announcement;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Курсы обязательны, только если не выбрано "все ученики" — обычный
        // required_if тут не подходит, чекбокс просто отсутствует в payload,
        // когда не отмечен.
        $allStudents = $this->boolean('all_students');

        return [
            'message' => 'required|string|max:2000',
            'all_students' => 'nullable|boolean',
            'course_ids' => [$allStudents ? 'nullable' : 'required', 'array'],
            'course_ids.*' => 'integer|exists:courses,id',
            'expires_at' => 'nullable|date|after:now',
        ];
    }
}
