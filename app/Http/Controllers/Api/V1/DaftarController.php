<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DaftarMandiriRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Services\RegistrationService;
use App\Services\StudentImportService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DaftarController extends Controller
{
    use ApiResponse;

    public function __construct(
        private RegistrationService $registration,
        private StudentImportService $importer,   // ← inject importer
    ) {}

    public function mandiri(DaftarMandiriRequest $request): JsonResponse
    {
        try {
            $result = $this->registration->registerMandiri($request->validated());
        } catch (DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'student' => [
                'id'           => $result['student']->id,
                'student_code' => $result['student']->student_code,
                'name'         => $result['student']->name,
            ],
            'invoice' => [
                'id'              => $result['invoice']->id,
                'invoice_number'  => $result['invoice']->invoice_number,
                'total_amount'    => $result['invoice']->total_amount,
                'discount_amount' => $result['invoice']->discount_amount,
                'due_date'        => $result['invoice']->due_date,
                'status'          => $result['invoice']->status,
            ],
        ], 'Pendaftaran berhasil. Silakan lakukan pembayaran sesuai tagihan.', 201);
    }

    // DaftarController
    public function mandiriBayar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoice_ids'   => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['exists:invoices,id'],
            'proof'         => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $path = $request->file('proof')->store('payments', 'local');

        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $path) {
            foreach ($data['invoice_ids'] as $id) {
                $invoice = \App\Models\Invoice::with('student')->findOrFail($id);
                Payment::create([
                    'invoice_id'    => $invoice->id,
                    'proof_file'    => $path,
                    'uploader_type' => 'parent',
                    'uploader_id'   => $invoice->student->parent_id,
                    'status'        => 'menunggu_verifikasi',
                ]);
                $invoice->update(['status' => 'menunggu_verifikasi']);
            }
        });

        return $this->success(null, 'Bukti pembayaran terkirim. Menunggu verifikasi Admin Keuangan.');
    }

    /* ---------- INSTANSI (publik, school_id dari body) ---------- */

    public function instansi(Request $request): JsonResponse
    {
        $data = $this->validateInstansi($request);
        $schoolId = $this->ensureMouSchool($data['school_id']);
        unset($data['school_id']); // sisanya (termasuk program_id) diteruskan ke service

        try {
            $result = $this->registration->registerInstansi($data, $schoolId);
        } catch (DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'student' => [
                'id'           => $result['student']->id,
                'student_code' => $result['student']->student_code,
                'name'         => $result['student']->name,
            ],
            'invoice' => [
                'id'             => $result['invoice']->id,
                'invoice_number' => $result['invoice']->invoice_number,
                'total_amount'   => $result['invoice']->total_amount,
                'due_date'       => $result['invoice']->due_date,
                'status'         => $result['invoice']->status,
            ],
        ], 'Murid berhasil didaftarkan.', 201);
    }

    public function instansiPreview(Request $request): JsonResponse
    {
        $request->validate([
            'file'      => ['required', 'file', 'mimes:xlsx,xls'],
            'school_id' => ['required', 'exists:schools,id'],
        ]);
        $this->ensureMouSchool($request->school_id);

        $rows = $this->importer->parseAndValidate($request->file('file'));
        $errorCount = collect($rows)->filter(fn($r) => ! empty($r['errors']))->count();

        return $this->success([
            'total'       => count($rows),
            'valid_count' => count($rows) - $errorCount,
            'error_count' => $errorCount,
            'rows'        => collect($rows)->map(fn($r) => [
                'row'    => $r['row'],
                'name'   => $r['data']['name'] ?? null,
                'valid'  => empty($r['errors']),
                'errors' => $r['errors'],
            ])->values(),
        ], $errorCount ? 'Ada baris yang perlu diperbaiki.' : 'Semua baris valid.');
    }

    public function instansiImport(Request $request): JsonResponse
    {
        $request->validate([
            'file'       => ['required', 'file', 'mimes:xlsx,xls'],
            'school_id'  => ['required', 'exists:schools,id'],
            'program_id' => ['required', 'exists:programs,id'],   // ← program, bukan class
        ]);
        $schoolId = $this->ensureMouSchool($request->school_id);

        $rows = $this->importer->parseAndValidate($request->file('file'));
        $imported = 0;
        $skipped = [];

        foreach ($rows as $row) {
            if (! empty($row['errors'])) {
                $skipped[] = ['row' => $row['row'], 'reason' => implode(', ', $row['errors'])];
                continue;
            }
            try {
                $payload = $row['data'] + ['photo_permission' => true, 'program_id' => (int) $request->program_id];
                $this->registration->registerInstansi($payload, $schoolId);
                $imported++;
            } catch (DomainException $e) {
                $skipped[] = ['row' => $row['row'], 'reason' => $e->getMessage()];
            }
        }

        return $this->success(['imported' => $imported, 'skipped' => $skipped], 'Proses import selesai.');
    }

    /* ---------- helpers ---------- */

    private function validateInstansi(Request $request): array
    {
        return $request->validate([
            'school_id'        => ['required', 'exists:schools,id'],
            'program_id'       => ['required', 'exists:programs,id'],
            'parent_name'      => ['required', 'string', 'max:120'],
            'greeting'         => ['nullable', 'in:ayah,bunda'],
            'phone'            => ['required', 'string', 'max:20'],
            'phone_alt'        => ['nullable', 'string', 'max:20'],
            'name'             => ['required', 'string', 'max:120'],
            'birth_date'       => ['required', 'date'],
            'gender'           => ['required', 'in:L,P'],
            'shirt_size'       => ['nullable', 'string', 'max:10'],
            'school_grade'     => ['nullable', 'string', 'max:20'],
            'allergy_notes'    => ['nullable', 'string'],
            'photo_permission' => ['boolean'],
        ]);
    }

    private function ensureMouSchool($schoolId): int
    {
        abort_unless(
            School::where('is_mou', true)->whereKey($schoolId)->exists(),
            422,
            'Sekolah tidak valid atau belum ber-MOU.'
        );
        return (int) $schoolId;
    }

    public function instansiBayar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoice_ids'   => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['exists:invoices,id'],
            'proof'         => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $path = $request->file('proof')->store('payments', 'local');

        DB::transaction(function () use ($data, $path) {
            foreach ($data['invoice_ids'] as $id) {
                $invoice = Invoice::with('student')->findOrFail($id);
                Payment::create([
                    'invoice_id'    => $invoice->id,
                    'proof_file'    => $path,
                    'uploader_type' => 'school_admin',
                    'uploader_id'   => $invoice->student->school_id,
                    'status'        => 'menunggu_verifikasi',
                ]);
                $invoice->update(['status' => 'menunggu_verifikasi']);
            }
        });

        return $this->success(null, 'Bukti pembayaran terkirim. Menunggu verifikasi admin.');
    }
}
