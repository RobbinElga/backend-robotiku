<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ParentLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'  => ['required_without:phone', 'nullable', 'string'],  // nama anak
            'phone' => ['required_without:name', 'nullable', 'string'],   // nomor HP ortu
        ];
    }

    public function messages(): array
    {
        return [
            'name.required_without'  => 'Isi nama anak atau nomor HP.',
            'phone.required_without' => 'Isi nomor HP atau nama anak.',
        ];
    }
}
