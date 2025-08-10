<?php

namespace App\Http\Requests\Admin\Session;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
             'date'             => ['required', 'date'],  
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:active,cancelled'],
        ];
    }

    public function attributes(): array
    {
        return [
             'date'             => ['required', 'date'],  
            'start_time' => 'Время начала',
            'duration_minutes' => 'Длительность',
            'status' => 'Статус',
        ];
    }
}
