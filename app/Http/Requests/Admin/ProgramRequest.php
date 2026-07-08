<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:120'],
            'level'            => ['nullable', 'string', 'max:60'],
            'registration_fee' => ['required', 'integer', 'min:0'],
            'price_per_cycle'  => ['required', 'integer', 'min:0'],
            'is_active'        => ['boolean'],
            'is_visible' => ['sometimes', 'boolean'],
        ];
    }
}
