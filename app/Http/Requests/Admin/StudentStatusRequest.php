<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StudentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:aktif,cuti,nonaktif,lulus,berhenti'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}