<?php

namespace App\Http\Requests\Murid;

use Illuminate\Foundation\Http\FormRequest;

class ParentProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'phone'      => ['required', 'string', 'max:20'],
        ];
    }
}
