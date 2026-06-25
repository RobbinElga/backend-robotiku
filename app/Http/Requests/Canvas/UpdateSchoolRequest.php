<?php

namespace App\Http\Requests\Canvas;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['sometimes', 'required', 'string', 'max:150'],
            'address'      => ['nullable', 'string', 'max:500'],
            'pic_name'     => ['nullable', 'string', 'max:120'],
            'contact'      => ['nullable', 'string', 'max:30'],
            'bank_account' => ['nullable', 'string', 'max:50'],
            // status TIDAK diubah di sini (ada endpoint khusus di 6b)
        ];
    }
}
