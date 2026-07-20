<?php

namespace App\Http\Controllers\Api\V1\Sekolah;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\SchoolSettlement;
use App\Services\WhatsappService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolPaymentController extends Controller
{
    use ApiResponse;
    public function __construct(private WhatsappService $wa) {}

    private function schoolId(Request $r): int
    {
        abort_unless($r->user() instanceof SchoolAdmin, 403, 'Khusus Admin Sekolah.');
        return $r->user()->school_id;
    }

    /** Lapis-1: pembayaran ortu masuk (menunggu verifikasi). */
    public function pendingPayments(Request $r): JsonResponse
    {
        $sid = $this->schoolId($r);
        $payments = Payment::with(['invoice.student:id,name,student_code,school_id,parent_id', 'invoice.student.parent:id,name,phone,greeting'])
            ->where('status', 'menunggu_verifikasi')
            ->whereHas('invoice.student', fn($q) => $q->where('school_id', $sid)->where('registration_type', 'instansi'))
            ->latest()->get();
        return $this->success($payments, 'Pembayaran menunggu verifikasi.');
    }

    public function verify(Request $r, Payment $payment): JsonResponse
    {
        $sid = $this->schoolId($r);
        $payment->load('invoice.student.parent');
        abort_unless(optional($payment->invoice->student)->school_id === $sid, 403);
        $data = $r->validate(['action' => ['required', 'in:approve,reject'], 'note' => ['nullable', 'string']]);

        if ($data['action'] === 'approve') {
            $payment->update([
                'status' => 'diverifikasi',
                'verified_at' => now(),
                'verified_by_school_admin' => $r->user()->id,   // ← Admin Sekolah yang verifikasi
                'notes' => $data['note'] ?? null,
            ]);
            $payment->invoice->update(['status' => 'lunas']);
            optional($payment->invoice->student)->update(['is_verified' => true]);
            $st = $payment->invoice->student;
            $p = $st?->parent;
            if ($p?->phone) $this->wa->sendTemplate('wa_tpl_payment_confirmed', $p->phone, ['sapaan' => $this->sapaan($p->greeting), 'nama_anak' => $st->name]);
        } else {
            $payment->update([
                'status' => 'ditolak',
                'verified_by_school_admin' => $r->user()->id,   // catat penolak juga
                'notes' => $data['note'] ?? null,
            ]);
            $payment->invoice->update(['status' => 'belum_bayar']);
        }
        return $this->success(null, 'Pembayaran diproses.');
    }

    /** Lapis-2: invoice lunas yang belum disetor + ringkasan komisi. */
    public function availableInvoices(Request $r): JsonResponse
    {
        $sid = $this->schoolId($r);
        $school = School::findOrFail($sid);
        $invoices = Invoice::with('student:id,name,student_code')
            ->whereHas('student', fn($q) => $q->where('school_id', $sid)->where('registration_type', 'instansi'))
            ->where('status', 'lunas')
            ->whereDoesntHave('settlements', fn($q) => $q->whereIn('school_settlements.status', ['menunggu_verifikasi', 'diverifikasi']))
            ->latest()->get();

        $gross = (int) $invoices->sum('total_amount');
        $comm  = (int) round($gross * ((float) $school->commission_percent / 100));
        return $this->success([
            'invoices' => $invoices,
            'gross' => $gross,
            'commission_percent' => (float) $school->commission_percent,
            'commission_amount' => $comm,
            'net' => $gross - $comm,
        ], 'Invoice siap disetor.');
    }

    public function createSettlement(Request $r): JsonResponse
    {
        $sid = $this->schoolId($r);
        $school = School::findOrFail($sid);
        $data = $r->validate([
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['exists:invoices,id'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $invoices = Invoice::whereIn('id', $data['invoice_ids'])
            ->whereHas('student', fn($q) => $q->where('school_id', $sid))
            ->where('status', 'lunas')
            ->whereDoesntHave('settlements', fn($q) => $q->whereIn('school_settlements.status', ['menunggu_verifikasi', 'diverifikasi']))
            ->get();
        if ($invoices->isEmpty()) return $this->error('Tidak ada invoice valid untuk disetor.', 422);

        $gross = (int) $invoices->sum('total_amount');
        $comm  = (int) round($gross * ((float) $school->commission_percent / 100));
        $path  = $r->file('proof')->store('settlements', 'local');

        $settlement = SchoolSettlement::create([
            'school_id' => $sid,
            'gross_amount' => $gross,
            'commission_percent' => $school->commission_percent,
            'commission_amount' => $comm,
            'net_amount' => $gross - $comm,
            'bank_account_id' => $data['bank_account_id'] ?? null,
            'proof_file' => $path,
            'status' => 'menunggu_verifikasi',
            'created_by' => $r->user()->id,
        ]);
        $settlement->invoices()->attach($invoices->pluck('id'));

        return $this->success($settlement, 'Setoran terkirim, menunggu verifikasi Admin Keuangan.', 201);
    }

    public function settlements(Request $r): JsonResponse
    {
        $sid = $this->schoolId($r);
        return $this->success(SchoolSettlement::where('school_id', $sid)->latest()->paginate(15), 'Riwayat setoran.');
    }

    private function sapaan(?string $g): string
    {
        return match ($g) {
            'ayah' => 'Ayah',
            'bunda' => 'Bunda',
            default => 'Ayah/Bunda'
        };
    }

    public function showSettlement(Request $r, SchoolSettlement $settlement): JsonResponse
    {
        $sid = $this->schoolId($r);
        abort_unless($settlement->school_id === $sid, 403);

        $settlement->load(['invoices.student:id,name,student_code']);

        return $this->success($settlement, 'Detail setoran.');
    }

    /** Riwayat pembayaran ortu yang sudah diproses (diverifikasi / ditolak). */
    public function paymentHistory(Request $r): JsonResponse
    {
        $sid = $this->schoolId($r);
        $q = Payment::with(['invoice.student:id,name,student_code,school_id'])
            ->whereIn('status', ['diverifikasi', 'ditolak'])
            ->whereHas('invoice.student', fn($x) => $x->where('school_id', $sid)->where('registration_type', 'instansi'))
            ->when($r->filled('status'), fn($x) => $x->where('status', $r->status))
            ->orderByDesc('verified_at')->latest();

        return $this->success($q->paginate(20), 'Riwayat verifikasi pembayaran.');
    }

    /** Daftar semua tagihan (invoice) murid instansi sekolah + ringkasan status. */
    public function studentInvoices(Request $r): JsonResponse
    {
        $sid = $this->schoolId($r);

        $base = Invoice::whereHas('student', fn($q) => $q->where('school_id', $sid)->where('registration_type', 'instansi'));

        $summary = [
            'belum_bayar' => (clone $base)->where('status', 'belum_bayar')->count(),
            'menunggu'    => (clone $base)->where('status', 'menunggu_verifikasi')->count(),
            'lunas'       => (clone $base)->where('status', 'lunas')->count(),
            'outstanding' => (int) (clone $base)->whereIn('status', ['belum_bayar', 'menunggu_verifikasi'])->sum('total_amount'),
        ];

        $invoices = (clone $base)
            ->with('student:id,name,student_code')
            ->when($r->filled('status'), fn($x) => $x->where('status', $r->status))
            ->when($r->filled('search'), function ($x) use ($r) {
                $s = $r->search;
                $x->whereHas('student', fn($w) => $w->where('name', 'like', "%$s%")->orWhere('student_code', 'like', "%$s%"));
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return $this->success(['summary' => $summary, 'invoices' => $invoices], 'Tagihan murid.');
    }
}
