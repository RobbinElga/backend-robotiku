<?php

namespace App\Http\Requests\Bayar;

use Illuminate\Foundation\Http\FormRequest;

class ParentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'phone'      => ['required', 'string', 'max:20'],
            'file'       => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
