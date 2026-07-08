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
                'status'       => $a->status,
                'score'        => $a->score,
                'report'      => $a->report,
                'has_photo'   => (bool) $a->photo,
                'photo'       => $a->photo,
                'attended_at' => $a->attended_at,
                'trainer_name' => optional($a->trainer)->name,
            ]);

        $summary = [
            'hadir'            => $attendances->where('status', 'hadir')->count(),
            'izin'             => $attendances->where('status', 'izin')->count(),
            'sakit'            => $attendances->where('status', 'sakit')->count(),
            'tanpa_keterangan' => $attendances->where('status', 'tanpa_keterangan')->count(),
            'total_sesi'       => $attendances->count(),
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
