<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DaftarMandiriRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:120'],     // nama anak
            'birth_date'       => ['required', 'date'],
            'gender'           => ['required', 'in:L,P'],
            'shirt_size'       => ['nullable', 'string', 'max:10'],
            'school_origin'    => ['nullable', 'string', 'max:150'],
            'school_grade'     => ['nullable', 'string', 'max:20'],
            'allergy_notes'    => ['nullable', 'string', 'max:500'],
            'photo_permission' => ['required', 'boolean'],
            'parent_name'      => ['required', 'string', 'max:120'],
            'phone'            => ['required', 'string', 'max:20'],
            'class_id'         => ['required', 'integer', 'exists:classes,id'],
            'promo_code'       => ['nullable', 'string', 'max:50'],
        ];
    }
}
