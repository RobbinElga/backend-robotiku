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
            ->paginate($request->integer('per_page', 20));

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

        return $this->success([
            'payment_status' => $payment->status,
            'invoice_status' => $payment->invoice->fresh()->status,
        ], $request->action === 'approve' ? 'Pembayaran diverifikasi (lunas).' : 'Pembayaran ditolak.');
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
