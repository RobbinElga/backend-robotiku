<?php

namespace App\Services;

use App\Models\Student;

class ProgressService
{
    public function forStudent(Student $student): array
    {
        $attendances = $student->attendances()
            ->orderByDesc('attended_at')
            ->get(['id', 'class_id', 'status', 'report', 'photo', 'attended_at'])
            ->map(fn($a) => [
                'id'          => $a->id,
                'status'      => $a->status,
                'report'      => $a->report,
                'has_photo'   => (bool) $a->photo,
                'attended_at' => $a->attended_at,
            ]);

        $summary = [
            'hadir'       => $attendances->where('status', 'hadir')->count(),
            'izin'        => $attendances->where('status', 'izin')->count(),
            'tidak_hadir' => $attendances->where('status', 'tidak_hadir')->count(),
            'total_sesi'  => $attendances->count(),
        ];

        return [
            'student' => [
                'id'           => $student->id,
                'name'         => $student->name,
                'student_code' => $student->student_code,
                'status'       => $student->status,
            ],
            'summary'     => $summary,
            'attendances' => $attendances->values(),
        ];
    }
}
