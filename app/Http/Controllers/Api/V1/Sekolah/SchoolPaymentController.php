<?php

namespace App\Http\Controllers\Api\V1\Sekolah;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentStatusLog;
use App\Models\SchoolAdmin;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SchoolPaymentController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $admin = $request->user();
        if (! $admin instanceof SchoolAdmin) return $this->error('Khusus Admin Sekolah.', 403);

        $invoices = Invoice::whereHas('student', fn($q) => $q->where('school_id', $admin->school_id))
            ->whereIn('status', ['belum_bayar', 'menunggu_verifikasi'])
            ->with('student:id,name,student_code')
            ->orderByRaw("FIELD(status,'belum_bayar','menunggu_verifikasi')")
            ->get(['id', 'invoice_number', 'student_id', 'total_amount', 'status', 'due_date']);

        $belum = $invoices->where('status', 'belum_bayar');

        return $this->success([
            'invoices'           => $invoices,
            'total_belum_bayar'  => (float) $belum->sum(fn($i) => (float) $i->total_amount),
            'count_belum_bayar'  => $belum->count(),
        ], 'Tagihan instansi.');
    }

    public function collectiveUpload(Request $request): JsonResponse
    {
        $admin = $request->user();
        if (! $admin instanceof SchoolAdmin) return $this->error('Khusus Admin Sekolah.', 403);

        $request->validate([
            'file'          => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'invoice_ids'   => ['nullable', 'array'],
            'invoice_ids.*' => ['integer'],
        ]);

        $q = Invoice::whereHas('student', fn($x) => $x->where('school_id', $admin->school_id))
            ->where('status', 'belum_bayar');
        if ($request->filled('invoice_ids')) $q->whereIn('id', $request->invoice_ids);
        $invoices = $q->get();

        if ($invoices->isEmpty()) return $this->error('Tidak ada tagihan yang bisa dibayar.', 422);

        $path = $request->file('file')->storeAs(
            'payments',
            Str::uuid() . '.' . $request->file('file')->getClientOriginalExtension(),
            'local'
        );

        DB::transaction(function () use ($invoices, $path, $admin) {
            foreach ($invoices as $inv) {
                $payment = Payment::create([
                    'invoice_id' => $inv->id,
                    'proof_file' => $path,
                    'uploader_type' => 'school_admin',
                    'uploader_id' => $admin->id,
                    'status' => 'menunggu_verifikasi',
                ]);
                PaymentStatusLog::create([
                    'payment_id' => $payment->id,
                    'old_status' => null,
                    'new_status' => 'menunggu_verifikasi',
                    'notes' => 'Bukti pembayaran kolektif diunggah.',
                    'changed_by' => null,
                ]);
                $inv->update(['status' => 'menunggu_verifikasi']);
            }

            $recipients = User::whereIn('role', ['admin_keuangan', 'super_admin'])->where('is_active', true)->pluck('id');
            foreach ($recipients as $uid) {
                Notification::create([
                    'recipient_type' => 'user',
                    'recipient_id' => $uid,
                    'title' => 'Pembayaran Kolektif',
                    'message' => "Pembayaran kolektif: {$invoices->count()} tagihan menunggu verifikasi.",
                    'type' => 'pembayaran_baru',
                    'is_read' => false,
                ]);
            }
        });

        return $this->success(['count' => $invoices->count()], 'Bukti pembayaran kolektif terkirim. Menunggu verifikasi.');
    }
}
