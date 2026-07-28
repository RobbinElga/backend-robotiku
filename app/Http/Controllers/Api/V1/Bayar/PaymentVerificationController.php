<?php

namespace App\Http\Controllers\Api\V1\Bayar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bayar\VerifyPaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\PaymentVerificationService;
use App\Support\Phone;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class PaymentVerificationController extends Controller
{
    use ApiResponse;

    public function __construct(private PaymentVerificationService $verifier) {}

    /** Daftar pembayaran menunggu verifikasi. */
    public function index(Request $request): JsonResponse
    {
        $payments = Payment::with(['invoice.student:id,name,student_code'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when(! $request->filled('status'), fn($q) => $q->where('status', 'menunggu_verifikasi'))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20))
            ->through(fn($p) => $p->append('detail_route', 'verifikasi_route'));

        return $this->success($payments, 'Daftar pembayaran.');
    }

    /** Lihat file bukti (terproteksi, tidak via URL publik). */
    public function proof(Payment $payment): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        abort_unless($payment->proof_file && Storage::disk('local')->exists($payment->proof_file), 404);

        return response()->download(Storage::disk('local')->path($payment->proof_file));
    }

    public function verify(VerifyPaymentRequest $request, Payment $payment): JsonResponse
    {
        if ($payment->status !== 'menunggu_verifikasi') {
            return $this->error('Pembayaran ini sudah diproses.', 422);
        }

        $payment = $this->verifier->verify($payment, $request->action, $request->notes, $request->user()->id);

        // pembayaran disetujui → pendaftar resmi jadi siswa
        if ($request->action === 'approve') {
            $payment->loadMissing('invoice.student');
            optional($payment->invoice->student)->update(['is_verified' => true]);
        }

        return $this->success([
            'payment_status' => $payment->status,
            'invoice_status' => $payment->invoice->fresh()->status,
        ], $request->action === 'approve' ? 'Pembayaran diverifikasi (lunas).' : 'Pembayaran ditolak.');
    }

    /** Hapus pendaftaran yang belum jadi siswa (belum terverifikasi). */
    public function destroy(Payment $payment): JsonResponse
    {
        $payment->loadMissing('invoice.student');
        $student = optional($payment->invoice)->student;
        if (! $student) return $this->error('Data tidak ditemukan.', 404);
        if ($student->is_verified) return $this->error('Siswa sudah terverifikasi, tidak bisa dihapus dari sini.', 422);

        DB::transaction(function () use ($student) {
            $invoiceIds = DB::table('invoices')->where('student_id', $student->id)->pluck('id');
            $paymentIds = DB::table('payments')->whereIn('invoice_id', $invoiceIds)->pluck('id');

            DB::table('payment_status_logs')->whereIn('payment_id', $paymentIds)->delete();
            DB::table('payments')->whereIn('id', $paymentIds)->delete();
            DB::table('discount_usages')->where('student_id', $student->id)->delete();
            DB::table('invoices')->whereIn('id', $invoiceIds)->delete();
            DB::table('billing_months')->where('student_id', $student->id)->delete();
            DB::table('class_students')->where('student_id', $student->id)->delete();
            DB::table('student_status_logs')->where('student_id', $student->id)->delete();

            $parentId = $student->parent_id;
            $student->delete();

            // hapus ortu bila tak punya anak lain (khusus jalur mandiri)
            if ($parentId && ! \App\Models\Student::where('parent_id', $parentId)->exists()) {
                DB::table('parents')->where('id', $parentId)->delete();
            }
        });

        return $this->success(null, 'Pendaftaran dihapus.');
    }

    /** Generate link wa.me penagihan dari template settings. */
    public function waLink(Invoice $invoice): JsonResponse
    {
        $invoice->load('student.parent');

        $template = Setting::where('key', 'wa_message_template')->value('value')
            ?? 'Halo {nama_ortu}, tagihan {invoice} untuk {nama_anak} sebesar {total}. Mohon diselesaikan. Terima kasih.';

        $message = strtr($template, [
            '{nama_ortu}' => $invoice->student->parent?->name ?? 'Bapak/Ibu',
            '{nama_anak}' => $invoice->student->name,
            '{total}'     => 'Rp' . number_format((float) $invoice->total_amount, 0, ',', '.'),
            '{invoice}'   => $invoice->invoice_number,
        ]);

        $waPhone = Phone::toWa($invoice->student->parent?->phone);
        $url = 'https://wa.me/' . $waPhone . '?text=' . rawurlencode($message);

        return $this->success(['url' => $url, 'message' => $message, 'phone' => $waPhone], 'Link penagihan dibuat.');
    }
}
