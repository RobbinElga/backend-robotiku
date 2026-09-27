<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentBiodataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Student fields
            'name'             => ['sometimes', 'required', 'string', 'max:120'],
            'birth_date'       => ['nullable', 'date'],
            'gender'           => ['sometimes', 'required', 'in:L,P'],
            'shirt_size'       => ['nullable', 'string', 'max:10'],
            'school_origin'    => ['nullable', 'string', 'max:150'],
            'school_grade'     => ['nullable', 'string', 'max:20'],
            'address'          => ['nullable', 'string'],
            'allergy_notes'    => ['nullable', 'string', 'max:500'],
            'photo_permission' => ['nullable', 'boolean'],
            'program_id'       => ['nullable', 'integer', 'exists:programs,id'],
            'joined_at'        => ['nullable', 'date'],

            // Parent fields
            'parent_name'      => ['nullable', 'string', 'max:120'],
            'phone'            => ['nullable', 'string', 'max:20'],
            'greeting'         => ['nullable', 'in:ayah,bunda'],
            'phone_alt'        => ['nullable', 'string', 'max:20'],
        ];
    }
}
