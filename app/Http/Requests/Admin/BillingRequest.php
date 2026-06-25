<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'registration_fee' => ['required', 'numeric', 'min:0'],
            'price_per_cycle'  => ['required', 'numeric', 'min:0'],
        ];
    }
}
