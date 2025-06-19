<?php

namespace App\Http\Requests\Admin\Session;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id'    => ['required', 'exists:courses,id'],
            'date'         => ['required', 'date'],
            'start_time'   => ['required', 'date_format:H:i'],
            'end_time'     => ['required', 'date_format:H:i', 'after:start_time'],
            'status'       => ['required', 'in:active,cancelled'],
        ];
    }

    public function attributes(): array
    {
        return [
            'course_id'  => 'Курс',
            'date'       => 'Дата',
            'start_time' => 'Время начала',
            'end_time'   => 'Время окончания',
            'status'     => 'Статус',
        ];
    }
}
