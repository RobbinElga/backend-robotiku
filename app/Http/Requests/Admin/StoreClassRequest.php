<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:120'],
            'schedule'   => ['nullable', 'string', 'max:120'],
            'capacity'   => ['nullable', 'integer', 'min:1'],
            'trainer_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'trainer')],
        ];
    }
}
