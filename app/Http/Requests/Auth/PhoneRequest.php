<?php
namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class PhoneRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return ['phone' => ['required','string','min:6','max:32']];
    }
}
