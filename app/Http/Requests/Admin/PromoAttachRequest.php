<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PromoAttachRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // роут уже защищён миддлварью 'admin'
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64'],
        ];
    }
}
