<?php

namespace App\Services;

use App\Imports\StudentsImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class StudentImportService
{
    /** Baca file → array baris ternormalisasi + hasil validasi. */
    public function parseAndValidate(UploadedFile $file): array
    {
        $import = new StudentsImport;
        Excel::import($import, $file);

        $result = [];
        foreach ($import->rows as $i => $raw) {
            $rowNumber = $i + 2; // +1 header, +1 index mulai 0

            $data = [
                'name'         => trim((string) ($raw['nama'] ?? '')),
                'birth_date'   => $this->normalizeDate($raw['tanggal_lahir'] ?? null),
                'gender'       => strtoupper(trim((string) ($raw['gender'] ?? ''))),
                'shirt_size'   => $raw['ukuran_baju'] ?? null,
                'school_grade' => $raw['kelas_asal'] ?? null,
                'allergy_notes' => $raw['alergi'] ?? null,
            ];

            $validator = Validator::make($data, [
                'name'       => ['required', 'string', 'max:120'],
                'birth_date' => ['required', 'date'],
                'gender'     => ['required', 'in:L,P'],
                'shirt_size' => ['nullable', 'string', 'max:10'],
            ]);

            $result[] = [
                'row'    => $rowNumber,
                'data'   => $data,
                'errors' => $validator->errors()->all(),
            ];
        }

        return $result;
    }

    private function normalizeDate($value): ?string
    {
        if (! $value) {
            return null;
        }
        // Excel kadang kirim serial number tanggal
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }
        try {
            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
