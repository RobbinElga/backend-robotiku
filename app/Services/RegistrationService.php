<?php

namespace App\Services;

use App\Models\BillingMonth;
use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\User;
use App\Support\Phone;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistrationService
{
    public function __construct(private PromoService $promo) {}

    /**
     * Pendaftaran mandiri oleh Orang Tua.
     * @throws DomainException
     */
    public function registerMandiri(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $billing = BillingSetting::where('class_id', $data['class_id'])->first();
            if (! $billing) {
                throw new DomainException('Harga untuk program/kelas ini belum diatur.');
            }

            $registrationFee = (float) $billing->registration_fee;
            $pricePerCycle   = (float) $billing->price_per_cycle;

            // Validasi promo (opsional) — hanya untuk registration_fee
            $promo = null;
            $discount = 0.0;
            if (! empty($data['promo_code'])) {
                [$promo, $discount] = $this->promo->validate($data['promo_code'], $registrationFee);
            }

            // Cek duplikat: nama + tanggal lahir
            $dup = Student::where('name', $data['name'])
                ->whereDate('birth_date', $data['birth_date'])
                ->exists();
            if ($dup) {
                throw new DomainException('Siswa dengan nama & tanggal lahir yang sama sudah terdaftar.');
            }

            // Orang tua: cari via HP, buat kalau belum ada
            $phone = Phone::normalize($data['phone']);
            $parent = StudentParent::firstOrCreate(
                ['phone' => $phone],
                ['name' => $data['parent_name']]
            );

            // Buat siswa
            $student = Student::create([
                'student_code'      => $this->generateStudentCode(),
                'name'              => $data['name'],
                'birth_date'        => $data['birth_date'],
                'gender'            => $data['gender'],
                'shirt_size'        => $data['shirt_size'] ?? null,
                'school_origin'     => $data['school_origin'] ?? null,
                'school_grade'      => $data['school_grade'] ?? null,
                'allergy_notes'     => $data['allergy_notes'] ?? null,
                'photo_permission'  => $data['photo_permission'],
                'parent_id'         => $parent->id,
                'status'            => 'aktif',
                'registration_type' => 'mandiri',
            ]);

            // Siklus tagihan pertama
            $billingMonth = BillingMonth::create([
                'student_id'   => $student->id,
                'cycle_number' => 1,
                'period_month' => (int) now()->format('n'),
                'period_year'  => (int) now()->format('Y'),
                'status'       => 'aktif',
            ]);

            // Invoice pertama: registration_fee - diskon + price_per_cycle
            $total = max(0, $registrationFee - $discount) + $pricePerCycle;

            $invoice = Invoice::create([
                'invoice_number'   => 'TMP-' . Str::uuid(),
                'student_id'       => $student->id,
                'billing_month_id' => $billingMonth->id,
                'base_amount'      => $pricePerCycle,
                'registration_fee' => $registrationFee,
                'discount_amount'  => $discount,
                'total_amount'     => $total,
                'due_date'         => now()->addDays(7),
                'status'           => 'belum_bayar',
            ]);
            $invoice->update([
                'invoice_number' => 'INV-' . now()->format('Ymd') . '-' . str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT),
            ]);

            // Pakai promo (atomik: kuota + catat usage)
            if ($promo) {
                $this->promo->apply($promo, $student->id, $invoice->id);
            }

            // Notifikasi in-app ke Admin Keuangan & Super Admin
            $this->notifyNewRegistration($student->name);

            return ['student' => $student->fresh(), 'invoice' => $invoice->fresh()];
        });
    }

    private function generateStudentCode(): string
    {
        $prefix = 'ROBO-MDR';
        $last = Student::where('student_code', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('student_code');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    private function notifyNewRegistration(string $studentName): void
    {
        $recipients = User::whereIn('role', ['admin_keuangan', 'super_admin'])
            ->where('is_active', true)
            ->pluck('id');

        foreach ($recipients as $userId) {
            Notification::create([
                'recipient_type' => 'user',
                'recipient_id'   => $userId,
                'title'          => 'Pendaftaran Baru',
                'message'        => "Siswa baru terdaftar: {$studentName}.",
                'type'           => 'pendaftaran_baru',
                'is_read'        => false,
            ]);
        }
    }

    /**
     * Pendaftaran via instansi oleh Admin Sekolah (tanpa promo).
     * @throws \DomainException
     */
    public function registerInstansi(array $data, int $schoolId): array
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data, $schoolId) {
            $billing = \App\Models\BillingSetting::where('class_id', $data['class_id'])->first();
            if (! $billing) {
                throw new \DomainException('Harga untuk program/kelas ini belum diatur.');
            }

            $registrationFee = (float) $billing->registration_fee;
            $pricePerCycle   = (float) $billing->price_per_cycle;

            $dup = \App\Models\Student::where('name', $data['name'])
                ->whereDate('birth_date', $data['birth_date'])
                ->exists();
            if ($dup) {
                throw new \DomainException('Siswa dengan nama & tanggal lahir yang sama sudah terdaftar.');
            }

            $school = \App\Models\School::find($schoolId);

            $student = \App\Models\Student::create([
                'student_code'      => $this->generateInstansiCode(),
                'name'              => $data['name'],
                'birth_date'        => $data['birth_date'],
                'gender'            => $data['gender'],
                'shirt_size'        => $data['shirt_size'] ?? null,
                'school_origin'     => $school?->name,
                'school_grade'      => $data['school_grade'] ?? null,
                'allergy_notes'     => $data['allergy_notes'] ?? null,
                'photo_permission'  => $data['photo_permission'],
                'school_id'         => $schoolId,
                'status'            => 'aktif',
                'registration_type' => 'instansi',
            ]);

            $billingMonth = \App\Models\BillingMonth::create([
                'student_id'   => $student->id,
                'cycle_number' => 1,
                'period_month' => (int) now()->format('n'),
                'period_year'  => (int) now()->format('Y'),
                'status'       => 'aktif',
            ]);

            $total = $registrationFee + $pricePerCycle; // instansi: tanpa diskon

            $invoice = \App\Models\Invoice::create([
                'invoice_number'   => 'TMP-' . \Illuminate\Support\Str::uuid(),
                'student_id'       => $student->id,
                'billing_month_id' => $billingMonth->id,
                'base_amount'      => $pricePerCycle,
                'registration_fee' => $registrationFee,
                'discount_amount'  => 0,
                'total_amount'     => $total,
                'due_date'         => now()->addDays(14),
                'status'           => 'belum_bayar',
            ]);
            $invoice->update([
                'invoice_number' => 'INV-' . now()->format('Ymd') . '-' . str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT),
            ]);

            $this->notifyNewRegistration($student->name);

            return ['student' => $student->fresh(), 'invoice' => $invoice->fresh()];
        });
    }

    private function generateInstansiCode(): string
    {
        $prefix = 'ROBO-INS';
        $last = \App\Models\Student::where('student_code', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('student_code');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
