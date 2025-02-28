<?php

namespace App\Http\Requests\Admin\Exercise;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string',
            'ex_number' => 'required|string',
            'content' => 'nullable|string',
            'content_column_1_title' => 'nullable|string',
            'content_column_1_content' => 'nullable|string',
            'content_column_2_title' => 'nullable|string',
            'content_column_2_content' => 'nullable|string',
            'answer' => 'required|string',
            'comment' => 'nullable|string',
            'short_answer' => 'nullable|string',
            'text_spoiler' => 'nullable|string',
            'main_image' => 'nullable|file',
        ];
    }
}
