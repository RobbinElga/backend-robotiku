<?php

namespace App\Http\Requests\Canvas;

use Illuminate\Foundation\Http\FormRequest;

class StoreSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:150'],
            'address'         => ['nullable', 'string', 'max:500'],
            'pic_name'        => ['nullable', 'string', 'max:120'],
            'contact'         => ['nullable', 'string', 'max:30'],
            'bank_account'    => ['nullable', 'string', 'max:50'],
            'pipeline_status' => ['nullable', 'in:prospek,dalam_proses,sudah_mou,tidak_lanjut'],
        ];
    }
}
