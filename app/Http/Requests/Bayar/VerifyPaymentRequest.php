<?php

namespace App\Http\Requests\Bayar;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'in:approve,reject'],
            'notes'  => ['nullable', 'string', 'max:500', 'required_if:action,reject'],
        ];
    }

    public function messages(): array
    {
        return ['notes.required_if' => 'Alasan penolakan wajib diisi.'];
    }
}
