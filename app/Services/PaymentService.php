<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentStatusLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    /** Upload bukti bayar → invoice menunggu_verifikasi + log + notif. */
    public function uploadProof(Invoice $invoice, string $uploaderType, int $uploaderId, UploadedFile $file): Payment
    {
        return DB::transaction(function () use ($invoice, $uploaderType, $uploaderId, $file) {
            // simpan dengan nama UUID (bukan nama asli), di disk private
            $path = $file->storeAs(
                'payments',
                Str::uuid() . '.' . $file->getClientOriginalExtension(),
                'local'
            );

            $payment = Payment::create([
                'invoice_id'   => $invoice->id,
                'proof_file'   => $path,
                'uploader_type' => $uploaderType,
                'uploader_id'  => $uploaderId,
                'status'       => 'menunggu_verifikasi',
            ]);

            PaymentStatusLog::create([
                'payment_id' => $payment->id,
                'old_status' => null,
                'new_status' => 'menunggu_verifikasi',
                'notes'      => 'Bukti pembayaran diunggah.',
                'changed_by' => null, // ortu tidak punya akun user
            ]);

            $invoice->update(['status' => 'menunggu_verifikasi']);

            $this->notifyNewPayment($invoice);

            return $payment;
        });
    }

    private function notifyNewPayment(Invoice $invoice): void
    {
        $recipients = User::whereIn('role', ['admin_keuangan', 'super_admin'])
            ->where('is_active', true)
            ->pluck('id');

        foreach ($recipients as $userId) {
            Notification::create([
                'recipient_type' => 'user',
                'recipient_id'   => $userId,
                'title'          => 'Pembayaran Baru',
                'message'        => "Bukti pembayaran untuk invoice {$invoice->invoice_number} menunggu verifikasi.",
                'type'           => 'pembayaran_baru',
                'is_read'        => false,
            ]);
        }
    }
}
