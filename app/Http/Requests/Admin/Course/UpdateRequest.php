<?php

namespace App\Http\Requests\Admin\Course;
use Illuminate\Validation\Rule;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === \App\Models\User::ROLE_ADMIN;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|string|max:255',
            'old_price' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'path' => [
                'nullable',
                'string',
                Rule::unique('courses', 'path')
                    ->ignore($this->route('course')->id)
                    ->whereNull('deleted_at'),
            ],
            'html_title' => 'nullable|string|max:255',
            'html_description' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer|exists:categories,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'main_image' => 'nullable|image|max:2048',
            'schedule' => 'required|array|min:1',
            'schedule.*.day_of_week' => 'required|string|in:Mon,Tue,Wed,Thu,Fri,Sat,Sun',
            'schedule.*.start_time' => 'required|date_format:H:i',
            'schedule.*.duration_minutes' => 'required|integer|min:1',
        ];
    }
}
