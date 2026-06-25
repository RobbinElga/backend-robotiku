<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'    => ['required', 'string', 'max:200'],
            'slug'     => ['nullable', 'string', 'max:220', 'unique:articles,slug'],
            'content'  => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:60'],
            'status'   => ['required', 'in:draft,publish'],
            'cover'    => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }
}
