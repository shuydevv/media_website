<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            // Те же поля, что и на онбординге при регистрации (см.
            // Onboarding\ProfileRequest) — "name" там подписан как "Логин
            // в телеграм", сюда переносим то же самое под тем же ярлыком.
            'name' => ['required', 'string', 'max:100'],
        ];

        // Смена пароля необязательна — валидируем текущий/новый пароль,
        // только если поле нового пароля вообще заполнено.
        if ($this->filled('password')) {
            $rules['current_password'] = ['required', 'current_password'];
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed'];
        }

        return $rules;
    }
}
