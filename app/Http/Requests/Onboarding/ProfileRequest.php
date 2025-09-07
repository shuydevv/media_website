<?php
namespace App\Http\Requests\Onboarding;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        $ruTimezones = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, 'RU');
        return [
            'first_name' => ['required','string','max:100'],
            'last_name'  => ['nullable','string','max:100'],
            'name' => ['nullable', 'string', 'max:100'],
            'timezone'   => ['nullable', Rule::in($ruTimezones)],

            // Новый пароль обязателен и должен совпадать с подтверждением
            'password'              => ['required','string','min:8','confirmed'],
            'password_confirmation' => ['required','string','min:8'],
            
        ];
    }
}
