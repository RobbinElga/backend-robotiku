<?php

namespace App\Http\Controllers\Api\V1\Keuangan;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SchoolSettlement;
use App\Services\WhatsappService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    use ApiResponse;
    public function __construct(private WhatsappService $wa) {}

    /** Claim Pembayaran Sekolah — setoran menunggu. */
    public function pendingSettlements(): JsonResponse
    {
        return $this->success(
            SchoolSettlement::with(['school:id,name', 'invoices:id,invoice_number,total_amount'])->where('status', 'menunggu_verifikasi')->latest()->get(),
            'Setoran sekolah menunggu verifikasi.'
        );
    }

    public function verifySettlement(Request $r, SchoolSettlement $settlement): JsonResponse
    {
        $data = $r->validate(['action' => ['required', 'in:approve,reject'], 'note' => ['nullable', 'string']]);
        $settlement->update([
            'status' => $data['action'] === 'approve' ? 'diverifikasi' : 'ditolak',
            'verified_by' => $r->user()->id,
            'verified_at' => now(),
            'note' => $data['note'] ?? null,
        ]);
        return $this->success(null, 'Setoran diproses.');
    }

    /** Claim Pembayaran Kelas — pembayaran mandiri menunggu. */
    public function pendingMandiriPayments(): JsonResponse
    {
        $payments = Payment::with(['invoice.student:id,name,student_code,registration_type,parent_id', 'invoice.student.parent:id,name,phone,greeting'])
            ->where('status', 'menunggu_verifikasi')
            ->whereHas('invoice.student', fn($q) => $q->where('registration_type', 'mandiri'))
            ->latest()->get();
        return $this->success($payments, 'Pembayaran mandiri menunggu verifikasi.');
    }

    public function verifyMandiriPayment(Request $r, Payment $payment): JsonResponse
    {
        $payment->load('invoice.student.parent');
        abort_unless(optional($payment->invoice->student)->registration_type === 'mandiri', 403);
        $data = $r->validate(['action' => ['required', 'in:approve,reject'], 'note' => ['nullable', 'string']]);

        if ($data['action'] === 'approve') {
            $payment->update(['status' => 'diverifikasi', 'verified_by' => $r->user()->id, 'verified_at' => now(), 'notes' => $data['note'] ?? null]);
            $payment->invoice->update(['status' => 'lunas']);
            $st = $payment->invoice->student;
            $p = $st?->parent;
            if ($p?->phone) $this->wa->sendTemplate('wa_tpl_payment_confirmed', $p->phone, ['sapaan' => $this->sapaan($p->greeting), 'nama_anak' => $st->name]);
        } else {
            $payment->update(['status' => 'ditolak', 'notes' => $data['note'] ?? null]);
            $payment->invoice->update(['status' => 'belum_bayar']);
        }
        return $this->success(null, 'Pembayaran diproses.');
    }

    private function sapaan(?string $g): string
    {
        return match ($g) {
            'ayah' => 'Ayah',
            'bunda' => 'Bunda',
            default => 'Ayah/Bunda'
        };
    }

    public function settlements(Request $request): JsonResponse
    {
        $q = SchoolSettlement::with(['school:id,name', 'invoices:id,invoice_number,total_amount'])
            ->when($request->filled('status'), fn($x) => $x->where('status', $request->status))
            ->latest()->paginate($request->integer('per_page', 15));
        return $this->success($q, 'Setoran sekolah.');
    }
}
