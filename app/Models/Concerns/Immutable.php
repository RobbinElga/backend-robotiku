<?php

namespace App\Models\Concerns;

use RuntimeException;

/**
 * Tabel log INSERT-ONLY. Mencegah UPDATE & DELETE di level aplikasi.
 *
 * @method static void updating(\Closure|string $callback)
 * @method static void deleting(\Closure|string $callback)
 */
trait Immutable
{
    protected static function bootImmutable(): void
    {
        static::updating(function () {
            throw new RuntimeException('Log bersifat immutable: UPDATE tidak diizinkan.');
        });
        static::deleting(function () {
            throw new RuntimeException('Log bersifat immutable: DELETE tidak diizinkan.');
        });
    }
}
