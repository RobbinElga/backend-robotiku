<?php

namespace App\Http\Controllers\Api\V1\Ortu;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Models\Kelas;
use App\Models\Payment;
use App\Models\School;
use App\Models\Student;
use App\Support\MediaStorage;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentDashboardController extends Controller
{
    use ApiResponse;

    public function index(Request $r): JsonResponse
    {
        $data = $r->validate(['student_id' => ['required', 'exists:students,id']]);
        $s = Student::with('program:id,name', 'school')->findOrFail($data['student_id']);

        $school = $s->school;
        $scheme = $school?->payment_scheme ?? School::SCHEME_V1_DIRECT;
        $selfManaged = $scheme === School::SCHEME_V3_COLLECTIVE;

        // pertemuan per periode mengikuti kelas siswa (default 4 bila belum ada kelas)
        $perPeriod = (int) (Kelas::whereHas('students', fn($q) => $q->where('students.id', $s->id))
            ->value('meetings_per_period') ?: 4);
        $perPeriod = max(1, $perPeriod);

        $att = Attendance::where('student_id', $s->id)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');
        $hadir = (int) ($att['hadir'] ?? 0);

        // Sekolah kelola-sendiri / V3 kolektif -> ortu tidak melihat tagihan sama sekali
        $invoices = $selfManaged
            ? collect()
            : Invoice::where('student_id', $s->id)->latest()->get(['id', 'invoice_number', 'total_amount', 'status', 'due_date']);

        // Payment info branched per scheme
        if ($scheme === School::SCHEME_V3_COLLECTIVE) {
            $schoolName = $school?->name ?? 'sekolah';
            $paymentInfo = [
                'scheme'      => School::SCHEME_V3_COLLECTIVE,
                'type'        => 'collective',
                'school_name' => $schoolName,
                'banner'      => "Pembiayaan ekstrakurikuler dikelola langsung secara kolektif oleh pihak {$schoolName}. Tidak ada tagihan mandiri yang perlu dibayarkan.",
            ];
        } elseif ($scheme === School::SCHEME_V2_SCHOOL) {
            $paymentInfo = [
                'scheme'       => School::SCHEME_V2_SCHOOL,
                'type'         => 'school_managed',
                'school_name'  => $school?->name,
                'bank_account' => $school?->bank_account,
                'qris_image'   => $school?->qris_image ? asset('storage/' . $school->qris_image) : null,
                'qris_path'    => $school?->qris_image,
            ];
        } else {
            $paymentInfo = [
                'scheme'        => School::SCHEME_V1_DIRECT,
                'type'          => 'direct_robotiku',
                'bank_accounts' => BankAccount::where('is_active', true)->orderBy('bank_name')->get(['id', 'bank_name', 'account_number', 'account_holder']),
            ];
        }

        return $this->success([
            'student' => [
                'name'           => $s->name,
                'student_code'   => $s->student_code,
                'program'        => $s->program?->name,
                'school'         => $school?->name,
                'status'         => $s->status,
                'period_quota'   => $s->period_quota,
                'self_managed'   => $selfManaged,
                'payment_scheme' => $scheme,
            ],
            'self_managed'   => $selfManaged,
            'payment_scheme' => $scheme,
            'payment_info'   => $paymentInfo,
            'kpi' => [
                'hadir'           => $hadir,
                'periode_selesai' => intdiv($hadir, $perPeriod),
                'pekan_berjalan'  => $hadir % $perPeriod,
                'per_periode'     => $perPeriod,
                'tagihan_belum'   => $selfManaged ? 0 : $invoices->where('status', 'belum_bayar')->count(),
            ],
            'kehadiran' => [
                ['name' => 'Hadir', 'value' => (int) ($att['hadir'] ?? 0)],
                ['name' => 'Izin',  'value' => (int) ($att['izin'] ?? 0)],
                ['name' => 'Sakit', 'value' => (int) ($att['sakit'] ?? 0)],
                ['name' => 'Alpa',  'value' => (int) ($att['tanpa_keterangan'] ?? 0)],
            ],
            'invoices' => $invoices->values(),
        ], 'Dashboard ortu.');
    }

    public function pay(Request $r): JsonResponse
    {
        $data = $r->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'proof'      => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $invoice = Invoice::with('student.school')->findOrFail($data['invoice_id']);

        // pengaman: sekolah kelola-sendiri / V3 kolektif tidak menerima pembayaran dari ortu
        if ($invoice->student?->school?->isV3()) {
            return $this->error('Pembayaran untuk sekolah ini dikelola langsung secara kolektif oleh pihak sekolah.', 422);
        }

        $path = MediaStorage::store($r->file('proof'), 'payments');
        Payment::create([
            'invoice_id'    => $invoice->id,
            'proof_file'    => $path,
            'uploader_type' => 'parent',
            'uploader_id'   => $invoice->student->parent_id,
            'status'        => 'menunggu_verifikasi',
        ]);
        $invoice->update(['status' => 'menunggu_verifikasi']);

        return $this->success(null, 'Bukti pembayaran terkirim, menunggu verifikasi.');
    }
}
