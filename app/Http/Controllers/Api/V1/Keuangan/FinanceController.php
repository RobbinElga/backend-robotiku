<?php

namespace App\Http\Controllers\Api\V1\Keuangan;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolSettlement;
use App\Models\Student;
use App\Services\WhatsappService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        if ($settlement->status !== 'menunggu_verifikasi') {
            return $this->error('Setoran sudah diproses sebelumnya.', 422);
        }

        $data = $r->validate(['action' => ['required', 'in:approve,reject'], 'note' => ['nullable', 'string']]);

        DB::transaction(function () use ($r, $settlement, $data) {
            $isApprove = $data['action'] === 'approve';

            $settlement->update([
                'status' => $isApprove ? 'diverifikasi' : 'ditolak',
                'verified_by' => $r->user()->id,
                'verified_at' => now(),
                'note' => $data['note'] ?? null,
            ]);

            if ($isApprove) {
                $settlement->loadMissing('invoices');

                $invoiceIds = $settlement->invoices->pluck('id');
                if ($invoiceIds->isNotEmpty()) {
                    Invoice::whereIn('id', $invoiceIds)->update(['status' => 'lunas']);

                    Payment::whereIn('invoice_id', $invoiceIds)
                        ->where('status', '!=', 'diverifikasi')
                        ->update([
                            'status' => 'diverifikasi',
                            'verified_at' => now(),
                            'verified_by' => $r->user()->id,
                        ]);

                    $studentIds = $settlement->invoices->pluck('student_id')->filter()->unique();
                    if ($studentIds->isNotEmpty()) {
                        Student::whereIn('id', $studentIds)->update(['is_verified' => true]);
                    }
                }
            }
        });

        return $this->success(null, 'Setoran diproses.');
    }

    /** Lihat file bukti setoran sekolah (inline preview). */
    public function settlementProof(Request $r, SchoolSettlement $settlement): BinaryFileResponse
    {
        abort_unless($settlement->proof_file && Storage::disk('local')->exists($settlement->proof_file), 404);

        if ($r->boolean('download')) {
            return response()->download(Storage::disk('local')->path($settlement->proof_file));
        }

        return response()->file(Storage::disk('local')->path($settlement->proof_file), [
            'Content-Disposition' => 'inline',
        ]);
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
        if ($payment->status !== 'menunggu_verifikasi') {
            return $this->error('Pembayaran sudah diproses sebelumnya.', 422);
        }
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
