<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageStorage
{
    /** Simpan gambar → WebP (UUID) di disk aktif (local atau s3/cloud). Kembalikan path relatif. */
    public static function storeWebp(UploadedFile $file, string $dir, ?string $disk = null): string
    {
        $diskName = $disk ?: config('filesystems.default', 'local');
        $path = $dir . '/' . Str::uuid() . '.webp';

        $ext = strtolower($file->getClientOriginalExtension());
        $src = match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'png'         => @imagecreatefrompng($file->getRealPath()),
            'webp'        => @imagecreatefromwebp($file->getRealPath()),
            default       => null,
        };

        if (! $src || ! function_exists('imagewebp')) {
            return $file->storeAs($dir, Str::uuid() . '.' . $ext, $diskName);
        }

        imagepalettetotruecolor($src);
        imagealphablending($src, true);
        imagesavealpha($src, true);

        ob_start();
        imagewebp($src, null, 80);
        $content = (string) ob_get_clean();
        imagedestroy($src);

        Storage::disk($diskName)->put($path, $content);

        return $path;
    }
}
