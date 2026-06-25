<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DaftarInstansiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:120'],
            'birth_date'       => ['required', 'date'],
            'gender'           => ['required', 'in:L,P'],
            'shirt_size'       => ['nullable', 'string', 'max:10'],
            'school_grade'     => ['nullable', 'string', 'max:20'],
            'allergy_notes'    => ['nullable', 'string', 'max:500'],
            'photo_permission' => ['required', 'boolean'],
            'class_id'         => ['required', 'integer', 'exists:classes,id'],
        ];
    }
}
