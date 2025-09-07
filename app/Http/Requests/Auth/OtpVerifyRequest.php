<?php
namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class OtpVerifyRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return ['code' => ['required','digits:6']];
    }
}
