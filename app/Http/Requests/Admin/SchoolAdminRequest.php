<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('schoolAdmin')?->id;
        $creating = $this->isMethod('post');

        return [
            'school_id' => ['required', Rule::exists('schools', 'id')->where('is_mou', true)],
            'name'      => ['required', 'string', 'max:120'],
            'email'     => ['nullable', 'email', 'max:150', Rule::unique('school_admins', 'email')->ignore($id)],
            'phone'     => ['nullable', 'string', 'max:20', Rule::unique('school_admins', 'phone')->ignore($id)],
            'password'  => [$creating ? 'required' : 'nullable', 'string', 'min:6'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (! $this->email && ! $this->phone) {
                $v->errors()->add('email', 'Email atau nomor HP wajib diisi (salah satu).');
            }
        });
    }
}
