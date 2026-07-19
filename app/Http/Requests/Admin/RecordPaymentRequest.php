<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // роут уже защищён миддлварью 'admin'
    }

    public function rules(): array
    {
        return [
            'amount_rub' => ['required', 'numeric', 'min:0'],
            'billing_interval_days' => ['nullable', Rule::in([14, 30])],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
