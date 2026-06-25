<?php

namespace App\Http\Requests\Canvas;

use Illuminate\Foundation\Http\FormRequest;

class SchoolStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pipeline_status' => ['required', 'in:prospek,dalam_proses,sudah_mou,tidak_lanjut'],
            'note'            => ['nullable', 'string', 'max:500'],
        ];
    }
}
