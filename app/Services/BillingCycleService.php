<?php

namespace App\Services;

use App\Models\BillingMonth;
use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingCycleService
{
    /**
     * Dipanggil setelah absensi "hadir" tersimpan.
     * Tiap kelipatan 4 "hadir" → buat siklus + invoice baru.
     * Mengembalikan Invoice baru, atau null jika tidak ada yang dibuat.
     */
    public function handleAttendance(Student $student): ?Invoice
    {
        // Siswa CUTI / BERHENTI → counter berhenti, tidak ada invoice baru
        if ($student->status !== 'aktif') {
            return null;
        }

        $hadirCount = $student->attendances()->where('status', 'hadir')->count();

        // hanya saat tepat kelipatan 4 (4, 8, 12, ...)
        if ($hadirCount === 0 || $hadirCount % 4 !== 0) {
            return null;
        }

        $nextCycle = intdiv($hadirCount, 4) + 1;

        // idempoten: kalau siklus ini sudah pernah dibuat, jangan dobel
        if ($student->billingMonths()->where('cycle_number', $nextCycle)->exists()) {
            return null;
        }

        // ambil harga dari kelas Robotiku siswa
        $class = $student->classes()->first();
        $billing = $class?->billingSetting;
        if (! $billing) {
            return null; // harga belum diatur → tidak bisa menagih
        }

        $price = (float) $billing->price_per_cycle;

        return DB::transaction(function () use ($student, $nextCycle, $price) {
            $billingMonth = BillingMonth::create([
                'student_id'   => $student->id,
                'cycle_number' => $nextCycle,
                'period_month' => (int) now()->format('n'),
                'period_year'  => (int) now()->format('Y'),
                'status'       => 'aktif',
            ]);

            $invoice = Invoice::create([
                'invoice_number'   => 'TMP-' . Str::uuid(),
                'student_id'       => $student->id,
                'billing_month_id' => $billingMonth->id,
                'base_amount'      => $price,
                'registration_fee' => null,   // hanya invoice pertama
                'discount_amount'  => 0,
                'total_amount'     => $price,
                'due_date'         => now()->addDays(7),
                'status'           => 'belum_bayar',
            ]);
            $invoice->update([
                'invoice_number' => 'INV-' . now()->format('Ymd') . '-' . str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT),
            ]);

            return $invoice;
        });
    }
}
