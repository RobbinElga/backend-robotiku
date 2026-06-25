<?php

namespace App\Services;

use App\Models\DiscountCode;
use App\Models\DiscountUsage;
use DomainException;
use Illuminate\Support\Carbon;

class PromoService
{
    /**
     * Validasi kode promo terhadap registration_fee.
     * @return array{0: DiscountCode, 1: float}  [promo, nominalDiskon]
     * @throws DomainException
     */
    public function validate(string $code, float $registrationFee): array
    {
        $normalized = strtoupper(trim($code));

        $promo = DiscountCode::where('code', $normalized)
            ->where('is_active', true)
            ->first();

        if (! $promo) {
            throw new DomainException('Kode promo tidak ditemukan atau nonaktif.');
        }

        $today = Carbon::today();

        if ($promo->valid_from && $today->lt($promo->valid_from->startOfDay())) {
            throw new DomainException('Kode promo belum berlaku.');
        }
        if ($promo->valid_until && $today->gt($promo->valid_until->endOfDay())) {
            throw new DomainException('Kode promo sudah kedaluwarsa.');
        }
        if ($promo->quota > 0 && $promo->used_count >= $promo->quota) {
            throw new DomainException('Kuota kode promo sudah habis.');
        }

        $discount = $promo->type === 'percentage'
            ? $registrationFee * ((float) $promo->value / 100)
            : (float) $promo->value;

        $discount = min($discount, $registrationFee); // promo hanya untuk registration_fee

        return [$promo, round($discount, 2)];
    }

    /**
     * Pakai promo: increment kuota + catat usage.
     * WAJIB dipanggil di dalam DB::transaction (lihat RegistrationService).
     * @throws DomainException
     */
    public function apply(DiscountCode $promo, int $studentId, int $invoiceId): void
    {
        $locked = DiscountCode::whereKey($promo->id)->lockForUpdate()->first();

        if ($locked->quota > 0 && $locked->used_count >= $locked->quota) {
            throw new DomainException('Kuota kode promo sudah habis.');
        }

        $locked->increment('used_count');

        DiscountUsage::create([
            'discount_code_id' => $locked->id,
            'student_id'       => $studentId,
            'invoice_id'       => $invoiceId,
            'used_at'          => now(),
        ]);
    }
}
