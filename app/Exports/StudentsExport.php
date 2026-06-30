<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['Kode', 'Nama', 'Gender', 'Tipe', 'Status', 'Sekolah/Asal', 'Kelas Asal', 'Orang Tua', 'No. HP'];
    }

    public function map($s): array
    {
        return [
            $s->student_code,
            $s->name,
            $s->gender,
            $s->registration_type,
            $s->status,
            $s->school?->name ?? $s->school_origin,
            $s->school_grade,
            $s->parent?->name,
            $s->parent?->phone,
        ];
    }
}
