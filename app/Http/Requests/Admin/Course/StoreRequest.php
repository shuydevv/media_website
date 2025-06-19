<?php

namespace App\Http\Requests\Admin\Course;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // dd(auth()->user());
        return auth()->check() && auth()->user()->role === User::ROLE_ADMIN;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'schedule' => 'required|array|min:1',
            'schedule.*.day_of_week' => 'required|in:Mon,Tue,Wed,Thu,Fri,Sat,Sun',
            'schedule.*.start_time' => 'required|date_format:H:i',
            'schedule.*.duration_minutes' => 'required|integer|min:1',

            'price' => 'required|string|max:255',
            'old_price' => 'nullable|string|max:255',
            'main_image' => 'nullable|image|max:2048', // если будет загрузка
            'content' => 'nullable|string',
            'path' => [
                'nullable',
                'string',
                Rule::unique('courses', 'path')->whereNull('deleted_at'),
            ],
            'html_title' => 'nullable|string|max:255',
            'html_description' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
        ];
    }
}
