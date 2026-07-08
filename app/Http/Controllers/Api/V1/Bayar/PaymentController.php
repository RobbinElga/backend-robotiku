<?php

namespace App\Http\Controllers\Api\V1\Bayar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bayar\ParentTagihanRequest;
use App\Http\Requests\Bayar\ParentUploadRequest;
use App\Http\Requests\Bayar\SchoolUploadRequest;
use App\Models\Invoice;
use App\Models\SchoolAdmin;
use App\Models\Student;
use App\Services\PaymentService;
use App\Support\Phone;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(private PaymentService $payments) {}

    /** Ortu: lihat tagihan & riwayat anak (verifikasi via HP). */
    public function parentTagihan(ParentTagihanRequest $request): JsonResponse
    {
        $student = Student::with('parent')->find($request->student_id);

        if (! $student || optional($student->parent)->phone !== Phone::normalize($request->phone)) {
            return $this->error('Data tidak cocok. Periksa nomor HP.', 403);
        }

        $invoices = Invoice::where('student_id', $student->id)
            ->with(['payments' => fn($q) => $q->latest()])
            ->orderByDesc('created_at')
            ->get();

        return $this->success([
            'student'  => ['id' => $student->id, 'name' => $student->name, 'student_code' => $student->student_code],
            'invoices' => $invoices,
        ], 'Daftar tagihan.');
    }

    /** Ortu: upload bukti bayar (verifikasi via HP). */
    public function parentUpload(ParentUploadRequest $request): JsonResponse
    {
        $invoice = Invoice::with('student.parent')->find($request->invoice_id);

        if (! $invoice || optional($invoice->student->parent)->phone !== Phone::normalize($request->phone)) {
            return $this->error('Data tidak cocok. Periksa nomor HP.', 403);
        }

        if ($invoice->status === 'lunas') {
            return $this->error('Invoice sudah lunas.', 422);
        }

        $payment = $this->payments->uploadProof($invoice, 'parent', $invoice->student->parent_id, $request->file('file'));

        return $this->success(
            ['payment_id' => $payment->id, 'invoice_status' => 'menunggu_verifikasi'],
            'Bukti pembayaran terkirim. Menunggu verifikasi.',
            201
        );
    }

    /** Admin Sekolah: lihat tagihan murid sekolahnya. */
    public function schoolInvoices(Request $request): JsonResponse
    {
        $admin = $request->user();
        if (! $admin instanceof SchoolAdmin) {
            return $this->error('Akses ditolak: khusus Admin Sekolah.', 403);
        }

        $invoices = Invoice::whereHas('student', fn($q) => $q->where('school_id', $admin->school_id))
            ->with('student:id,name,student_code')
            ->when($request->filled('student_id'), fn($q) => $q->where('student_id', $request->student_id)) // ← tambah
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->success($invoices, 'Tagihan murid instansi.');
    }

    /** Admin Sekolah: upload bukti bayar untuk invoice muridnya. */
    public function schoolUpload(SchoolUploadRequest $request, Invoice $invoice): JsonResponse
    {
        $admin = $request->user();
        if (! $admin instanceof SchoolAdmin) {
            return $this->error('Akses ditolak: khusus Admin Sekolah.', 403);
        }

        $invoice->load('student');
        if ($invoice->student->school_id !== $admin->school_id) {
            return $this->error('Invoice bukan milik sekolah Anda.', 403);
        }
        if ($invoice->status === 'lunas') {
            return $this->error('Invoice sudah lunas.', 422);
        }

        $payment = $this->payments->uploadProof($invoice, 'school_admin', $admin->id, $request->file('file'));

        return $this->success(
            ['payment_id' => $payment->id, 'invoice_status' => 'menunggu_verifikasi'],
            'Bukti pembayaran terkirim. Menunggu verifikasi.',
            201
        );
    }
}
