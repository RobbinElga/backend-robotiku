<?php

namespace App\Http\Requests\Murid;

use Illuminate\Foundation\Http\FormRequest;

class EReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $grade = ['required', 'in:A,B,C,D,E'];

        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'class_id'   => ['required', 'integer', 'exists:classes,id'],
            'semester'   => ['required', 'in:1,2'],
            'year'       => ['required', 'integer', 'min:2020', 'max:2100'],

            'skill_building'    => $grade,
            'skill_imagination' => $grade,
            'skill_creativity'  => $grade,
            'skill_logic'       => $grade,

            'behavior_punctual'       => $grade,
            'behavior_stay'           => $grade,
            'behavior_communication'  => $grade,
            'behavior_responsibility' => $grade,

            'comments'  => ['nullable', 'string', 'max:2000'],
            'signature' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }
}
