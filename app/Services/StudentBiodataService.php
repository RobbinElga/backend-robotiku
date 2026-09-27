<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Student;
use App\Models\StudentParent;
use Illuminate\Support\Facades\DB;

class StudentBiodataService
{
    /**
     * Update student and associated parent biodata atomically.
     *
     * @param Student $student
     * @param array<string, mixed> $data
     * @return Student
     */
    public function update(Student $student, array $data): Student
    {
        DB::transaction(function () use ($student, $data) {
            $studentFields = array_intersect_key($data, array_flip([
                'name', 'birth_date', 'gender', 'shirt_size', 'school_origin',
                'school_grade', 'address', 'allergy_notes', 'photo_permission',
                'program_id', 'joined_at',
            ]));

            if (! empty($studentFields)) {
                $student->update($studentFields);
            }

            $parentFields = [];
            if (array_key_exists('parent_name', $data)) {
                $parentFields['name'] = $data['parent_name'];
            }
            if (array_key_exists('phone', $data)) {
                $parentFields['phone'] = $data['phone'];
            }
            if (array_key_exists('greeting', $data)) {
                $parentFields['greeting'] = $data['greeting'];
            }
            if (array_key_exists('phone_alt', $data)) {
                $parentFields['phone_alt'] = $data['phone_alt'];
            }

            if (! empty($parentFields)) {
                if ($student->parent) {
                    $student->parent->update($parentFields);
                } elseif (! empty($parentFields['name']) || ! empty($parentFields['phone'])) {
                    $parent = StudentParent::create($parentFields);
                    $student->update(['parent_id' => $parent->id]);
                }
            }
        });

        $student->refresh();

        return $student;
    }
}
