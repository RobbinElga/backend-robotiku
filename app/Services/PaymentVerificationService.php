<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PaymentVerificationService
{
    /** $action: 'approve' | 'reject' */
    public function verify(Payment $payment, string $action, ?string $notes, int $userId): Payment
    {
        return DB::transaction(function () use ($payment, $action, $notes, $userId) {
            $old = $payment->status;
            $invoice = $payment->invoice;

            if ($action === 'approve') {
                $new = 'diverifikasi';
                $invoice->update(['status' => 'lunas']);
            } else {
                $new = 'ditolak';
                $invoice->update(['status' => 'belum_bayar']); // boleh upload ulang
            }

            $payment->update([
                'status'      => $new,
                'verified_by' => $userId,
                'verified_at' => now(),
                'notes'       => $notes,
            ]);

            $payment->statusLogs()->create([
                'old_status' => $old,
                'new_status' => $new,
                'notes'      => $notes,
                'changed_by' => $userId,
            ]);

            return $payment->fresh();
        });
    }
}
