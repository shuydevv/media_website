<?php

namespace App\Http\Requests\Admin\Post;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
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
            'title2' => 'nullable|string',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'main_image' => 'nullable|file',
            'category_id' => 'required|integer|exists:categories,id',
            'tag_id' => 'nullable|array',
            'tag_ids.*' => 'nullable|integer|exists:tags,id',
            'multi_images' => 'nullable',
            'path' => 'nullable|string',
            'html_title' => 'nullable|string',
            'html_description' => 'nullable|string',
        ];
    }
}
