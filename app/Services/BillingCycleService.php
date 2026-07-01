<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\BillingMonth;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingCycleService
{
    /**
     * Dipanggil setelah absensi "hadir" tersimpan.
     * Tiap kelipatan 4 "hadir" → invoice siklus baru (harga dari Program siswa).
     * CUTI / BERHENTI → tidak ada invoice baru.
     */
    public function handleAttendance(Student $student): ?Invoice
    {
        if (in_array($student->status, ['cuti', 'berhenti'], true)) {
            return null;
        }

        $hadir = Attendance::where('student_id', $student->id)
            ->where('status', 'hadir')
            ->count();

        if ($hadir === 0 || $hadir % 4 !== 0) {
            return null; // belum genap kelipatan 4
        }

        // Invoice pertama = cycle 1 (saat daftar). Tiap 4 "hadir" → cycle berikutnya.
        $cycleNumber = intdiv($hadir, 4) + 1;

        // Cegah dobel bila absensi diedit / dipanggil ulang
        $exists = BillingMonth::where('student_id', $student->id)
            ->where('cycle_number', $cycleNumber)
            ->exists();
        if ($exists) {
            return null;
        }

        return DB::transaction(function () use ($student, $cycleNumber) {
            $student->loadMissing('program');
            $pricePerCycle = (float) ($student->program?->price_per_cycle ?? 0);

            $billingMonth = BillingMonth::create([
                'student_id'   => $student->id,
                'cycle_number' => $cycleNumber,
                'period_month' => (int) now()->format('n'),
                'period_year'  => (int) now()->format('Y'),
                'status'       => 'aktif',
            ]);

            $invoice = Invoice::create([
                'invoice_number'   => 'TMP-' . Str::uuid(),
                'student_id'       => $student->id,
                'billing_month_id' => $billingMonth->id,
                'base_amount'      => $pricePerCycle,
                'registration_fee' => null,          // hanya invoice pertama
                'discount_amount'  => 0,
                'total_amount'     => $pricePerCycle,
                'due_date'         => now()->addDays(7),
                'status'           => 'belum_bayar',
            ]);
            $invoice->update([
                'invoice_number' => 'INV-' . now()->format('Ymd') . '-' . str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT),
            ]);

            $this->notifyNewInvoice($student->name, $invoice->invoice_number);

            return $invoice->fresh();
        });
    }

    private function notifyNewInvoice(string $studentName, string $invoiceNumber): void
    {
        $recipients = User::whereIn('role', ['admin_keuangan', 'super_admin'])
            ->where('is_active', true)
            ->pluck('id');

        foreach ($recipients as $userId) {
            Notification::create([
                'recipient_type' => 'user',
                'recipient_id'   => $userId,
                'title'          => 'Tagihan Baru',
                'message'        => "Tagihan {$invoiceNumber} untuk {$studentName} telah dibuat.",
                'type'           => 'pembayaran_baru',
                'is_read'        => false,
            ]);
        }
    }
}
