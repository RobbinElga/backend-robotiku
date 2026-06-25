<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('article')->id;

        return [
            'title'    => ['sometimes', 'required', 'string', 'max:200'],
            'slug'     => ['sometimes', 'nullable', 'string', 'max:220', Rule::unique('articles', 'slug')->ignore($id)],
            'content'  => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:60'],
            'status'   => ['sometimes', 'required', 'in:draft,publish'],
            'cover'    => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }
}
