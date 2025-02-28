<?php

namespace App\Http\Requests\Admin\Shpargalka;

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
            'price' => 'required|string',
            'description' => 'nullable|string',
            'main_image' => 'nullable|file',
            'content' => 'nullable|string',
            'category_id' => 'required|integer|exists:categories,id',
            'multi_images' => 'nullable',
            'path' => 'nullable|string',
            'html_title' => 'nullable|string',
            'html_description' => 'nullable|string',
        ];
    }
}
