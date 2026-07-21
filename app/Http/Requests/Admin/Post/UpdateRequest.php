<?php

namespace App\Http\Requests\Admin\Post;

use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Validation\Rule;
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
        $post = $this->route('post');
        return [
            'title' => 'required|string',
            'title2' => 'nullable|string',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'main_image' => 'nullable|image|max:5120',
            'category_id' => 'required|integer|exists:categories,id',
            'tag_id' => 'nullable|array',
            'tag_ids.*' => 'nullable|integer|exists:tags,id',
            'multi_images' => 'nullable|array',
            'multi_images.*' => 'image|max:5120',
            'path'  => ['nullable','alpha_dash','max:150', Rule::unique('posts','path')->ignore($post->id)],
            'html_title' => 'nullable|string',
            'html_description' => 'nullable|string',
        ];
    }
}
