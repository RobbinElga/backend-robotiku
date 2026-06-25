<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->code))]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'code'        => ['required', 'string', 'max:50', 'unique:discount_codes,code'],
            'type'        => ['required', 'in:nominal,percentage'],
            'value'       => ['required', 'numeric', 'min:0'],
            'quota'       => ['required', 'integer', 'min:0'], // 0 = unlimited
            'valid_from'  => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active'   => ['boolean'],
        ];

        if ($this->input('type') === 'percentage') {
            $rules['value'][] = 'max:100';
        }

        return $rules;
    }
}
