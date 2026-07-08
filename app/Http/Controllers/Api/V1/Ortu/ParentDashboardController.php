<?php

namespace App\Http\Controllers\Api\V1\Ortu;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentDashboardController extends Controller
{
    use ApiResponse;

    public function index(Request $r): JsonResponse
    {
        $data = $r->validate(['student_id' => ['required', 'exists:students,id']]);
        $s = Student::with('program:id,name', 'school:id,name')->findOrFail($data['student_id']);

        $hadir = Attendance::where('student_id', $s->id)->where('status', 'hadir')->count();
        $att = Attendance::where('student_id', $s->id)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
        $invoices = Invoice::where('student_id', $s->id)->latest()->get(['id', 'invoice_number', 'total_amount', 'status', 'due_date']);

        return $this->success([
            'student' => ['name' => $s->name, 'student_code' => $s->student_code, 'program' => $s->program?->name, 'school' => $s->school?->name, 'status' => $s->status, 'period_quota' => $s->period_quota],
            'kpi' => ['hadir' => $hadir, 'periode_selesai' => intdiv($hadir, 4), 'pekan_berjalan' => $hadir % 4, 'tagihan_belum' => $invoices->whereIn('status', ['belum_bayar'])->count()],
            'kehadiran' => [
                ['name' => 'Hadir', 'value' => (int) ($att['hadir'] ?? 0)],
                ['name' => 'Izin', 'value' => (int) ($att['izin'] ?? 0)],
                ['name' => 'Sakit', 'value' => (int) ($att['sakit'] ?? 0)],
                ['name' => 'Alpa', 'value' => (int) ($att['tanpa_keterangan'] ?? 0)],
            ],
            'invoices' => $invoices,
        ], 'Dashboard ortu.');
    }

    public function pay(Request $r): JsonResponse
    {
        $data = $r->validate(['invoice_id' => ['required', 'exists:invoices,id'], 'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120']]);
        $invoice = Invoice::with('student')->findOrFail($data['invoice_id']);
        $path = $r->file('proof')->store('payments', 'local');
        Payment::create(['invoice_id' => $invoice->id, 'proof_file' => $path, 'uploader_type' => 'parent', 'uploader_id' => $invoice->student->parent_id, 'status' => 'menunggu_verifikasi']);
        $invoice->update(['status' => 'menunggu_verifikasi']);
        return $this->success(null, 'Bukti pembayaran terkirim, menunggu verifikasi.');
    }
}
