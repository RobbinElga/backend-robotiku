<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\WhatsappService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstansiPaymentController extends Controller
{
    use ApiResponse;
    public function __construct(private WhatsappService $wa) {}

    public function index(Request $r): JsonResponse
    {
        $payments = Payment::with([
            'invoice.student:id,name,student_code,school_id',
            'invoice.student.school:id,name',
            'invoice.student.parent:id,name,phone,greeting',
        ])
            ->where('status', $r->input('status', 'menunggu_verifikasi'))
            ->whereHas('invoice.student', fn($q) => $q->where('registration_type', 'instansi')
                ->when($r->filled('school_id'), fn($x) => $x->where('school_id', $r->school_id)))
            ->latest()
            ->paginate($r->integer('per_page', 15));

        return $this->success($payments, 'Pembayaran instansi.');
    }

    public function verify(Request $r, Payment $payment): JsonResponse
    {
        $payment->load('invoice.student.parent');
        abort_unless(optional($payment->invoice->student)->registration_type === 'instansi', 403);
        $data = $r->validate(['action' => ['required', 'in:approve,reject'], 'note' => ['nullable', 'string']]);

        if ($data['action'] === 'approve') {
            $payment->update(['status' => 'diverifikasi', 'verified_by' => $r->user()->id, 'verified_at' => now(), 'notes' => $data['note'] ?? null]);
            $payment->invoice->update(['status' => 'lunas']);
            $st = $payment->invoice->student;
            $p = $st?->parent;
            if ($p?->phone) $this->wa->sendTemplate('wa_tpl_payment_confirmed', $p->phone, ['sapaan' => $this->sapaan($p->greeting), 'nama_anak' => $st->name]);
        } else {
            $payment->update(['status' => 'ditolak', 'verified_by' => $r->user()->id, 'notes' => $data['note'] ?? null]);
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
}
