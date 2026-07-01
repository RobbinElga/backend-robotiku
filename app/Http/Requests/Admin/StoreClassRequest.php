<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'program_id' => ['required', 'exists:programs,id'],
            'name'       => ['required', 'string', 'max:120'],
            'schedule'   => ['nullable', 'string', 'max:120'],
            'capacity'   => ['required', 'integer', 'min:1'],
            'trainer_id' => ['nullable', 'exists:users,id'],
        ];
    }
}
