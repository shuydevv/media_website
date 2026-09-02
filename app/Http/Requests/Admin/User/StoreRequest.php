<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'name' => 'nullable|string|max:100',
            'email' => 'required|string|email|unique:users',
            'phone' => 'nullable|string|max:32',
            'role' => 'required|integer',
            // Выдача доступа сразу к нескольким курсам — каждый со своей
            // датой (access_until[course_id]), не общей на всех.
            'course_ids' => 'nullable|array',
            'course_ids.*' => 'integer|exists:courses,id',
            'access_until' => 'nullable|array',
            'access_until.*' => 'nullable|date',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            foreach ($this->input('course_ids', []) as $courseId) {
                if (empty($this->input("access_until.{$courseId}"))) {
                    $validator->errors()->add("access_until.{$courseId}", 'Укажите дату доступа для выбранного курса');
                }
            }
        });
    }

    public function messages() {
        return [
            'first_name.required' => 'Это поле необходимо для заполнения',
            'first_name.string' => 'Имя должно быть строкой',
            'email.required' => 'Это поле необходимо для заполнения',
            'email.string' => 'Почта должна быть строкой',
            'email.email' => 'Введите корректную электронную почту в формате mail@mail.com',
            'email.unique' => 'Пользователь с таким email уже существует',
        ];
    }
}
