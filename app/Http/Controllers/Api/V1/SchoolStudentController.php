<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DaftarInstansiRequest;
use App\Http\Requests\ImportExcelRequest;
use App\Models\SchoolAdmin;
use App\Services\RegistrationService;
use App\Services\StudentImportService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;

class SchoolStudentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private RegistrationService $registration,
        private StudentImportService $importer,
    ) {}

    public function store(DaftarInstansiRequest $request): JsonResponse
    {
        $admin = $request->user();
        if (! $admin instanceof SchoolAdmin) {
            return $this->error('Akses ditolak: khusus Admin Sekolah.', 403);
        }

        try {
            $result = $this->registration->registerInstansi($request->validated(), $admin->school_id);
        } catch (DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'student' => [
                'id'           => $result['student']->id,
                'student_code' => $result['student']->student_code,
                'name'         => $result['student']->name,
                'school_id'    => $result['student']->school_id,
            ],
            'invoice' => [
                'invoice_number' => $result['invoice']->invoice_number,
                'total_amount'   => $result['invoice']->total_amount,
                'due_date'       => $result['invoice']->due_date,
                'status'         => $result['invoice']->status,
            ],
        ], 'Murid berhasil didaftarkan.', 201);
    }

    public function previewExcel(ImportExcelRequest $request): JsonResponse
    {
        $admin = $request->user();
        if (! $admin instanceof SchoolAdmin) {
            return $this->error('Akses ditolak: khusus Admin Sekolah.', 403);
        }

        $rows = $this->importer->parseAndValidate($request->file('file'));
        $errorCount = collect($rows)->filter(fn($r) => ! empty($r['errors']))->count();

        return $this->success([
            'total'       => count($rows),
            'valid_count' => count($rows) - $errorCount,
            'error_count' => $errorCount,
            'rows'        => $rows,
        ], $errorCount ? 'Ada baris yang perlu diperbaiki.' : 'Semua baris valid.');
    }

    public function importExcel(ImportExcelRequest $request): JsonResponse
    {
        $admin = $request->user();
        if (! $admin instanceof SchoolAdmin) {
            return $this->error('Akses ditolak: khusus Admin Sekolah.', 403);
        }

        $rows = $this->importer->parseAndValidate($request->file('file'));

        $imported = [];
        $skipped  = [];

        foreach ($rows as $row) {
            if (! empty($row['errors'])) {
                $skipped[] = ['row' => $row['row'], 'reason' => implode(', ', $row['errors'])];
                continue;
            }

            try {
                $payload = $row['data'] + ['photo_permission' => true, 'class_id' => $request->class_id];
                $res = $this->registration->registerInstansi($payload, $admin->school_id);
                $imported[] = [
                    'row'          => $row['row'],
                    'student_code' => $res['student']->student_code,
                    'name'         => $res['student']->name,
                ];
            } catch (DomainException $e) {
                $skipped[] = ['row' => $row['row'], 'reason' => $e->getMessage()];
            }
        }

        return $this->success([
            'imported_count' => count($imported),
            'skipped_count'  => count($skipped),
            'imported'       => $imported,
            'skipped'        => $skipped,
        ], 'Proses import selesai.');
    }
}
