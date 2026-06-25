<?php

namespace App\Http\Requests\Bayar;

use Illuminate\Foundation\Http\FormRequest;

class SchoolUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120']];
    }
}
