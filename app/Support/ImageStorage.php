<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageStorage
{
    /** Simpan gambar → WebP (UUID) di disk yang dipilih. Kembalikan path relatif. */
    public static function storeWebp(UploadedFile $file, string $dir, string $disk = 'local'): string
    {
        $path = $dir . '/' . Str::uuid() . '.webp';
        $full = Storage::disk($disk)->path($path);

        @mkdir(dirname($full), 0775, true);

        $ext = strtolower($file->getClientOriginalExtension());
        $src = match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'png'         => @imagecreatefrompng($file->getRealPath()),
            'webp'        => @imagecreatefromwebp($file->getRealPath()),
            default       => null,
        };

        // fallback: kalau GD tidak ada / format tak didukung → simpan apa adanya
        if (! $src || ! function_exists('imagewebp')) {
            return $file->storeAs($dir, Str::uuid() . '.' . $ext, $disk);
        }

        imagepalettetotruecolor($src);
        imagealphablending($src, true);
        imagesavealpha($src, true);
        imagewebp($src, $full, 80);
        imagedestroy($src);

        return $path;
    }
}
