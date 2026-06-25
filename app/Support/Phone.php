<?php

namespace App\Support;

class Phone
{
    /** Normalisasi ke format 08XXXXXXXXXX. */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw); // buang non-angka
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '62')) {
            $digits = '0' . substr($digits, 2);     // +62 / 62 -> 0
        } elseif (! str_starts_with($digits, '0')) {
            $digits = '0' . $digits;                // 8xx -> 08xx
        }

        return $digits;
    }

    /** 08xxxx → 62xxxx untuk link wa.me. */
    public static function toWa(?string $raw): string
    {
        $n = self::normalize($raw) ?? '';
        return str_starts_with($n, '0') ? '62' . substr($n, 1) : $n;
    }
}
