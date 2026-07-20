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
            'program_id'          => ['required', 'exists:programs,id'],
            'school_id'           => ['nullable', 'exists:schools,id'],   // null = kelas mandiri
            'name'                => ['required', 'string', 'max:120'],
            'schedule'            => ['nullable', 'string', 'max:120'],
            'capacity'            => ['required', 'integer', 'min:1'],
            'trainers'            => ['nullable', 'array'],
            'trainers.*.trainer_id' => ['required', 'exists:users,id'],
            'trainers.*.role'       => ['required', 'in:utama,pengganti'],
            'meetings_per_period' => ['required', 'integer', 'min:1', 'max:52'],
            'total_periods'       => ['nullable', 'integer', 'min:1', 'max:100', Rule::requiredIf(fn() => empty($this->input('school_id')))],
        ];
    }
}
