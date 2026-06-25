<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDiscountRequest extends FormRequest
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
        $id = $this->route('discountCode')->id;

        $rules = [
            'code'        => ['sometimes', 'required', 'string', 'max:50', Rule::unique('discount_codes', 'code')->ignore($id)],
            'type'        => ['sometimes', 'required', 'in:nominal,percentage'],
            'value'       => ['sometimes', 'required', 'numeric', 'min:0'],
            'quota'       => ['sometimes', 'required', 'integer', 'min:0'],
            'valid_from'  => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active'   => ['sometimes', 'boolean'],
        ];

        if ($this->input('type') === 'percentage') {
            $rules['value'][] = 'max:100';
        }

        return $rules;
    }
}
