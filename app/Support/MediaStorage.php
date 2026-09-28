<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class MediaStorage
{
    /**
     * Dapatkan instance disk aktif atau disk spesifik.
     */
    public static function disk(?string $disk = null): Filesystem
    {
        return Storage::disk($disk ?: config('filesystems.default', 'local'));
    }

    /**
     * Simpan file unggahan ke disk aktif (local atau s3/cloud).
     */
    public static function store(UploadedFile $file, string $dir, ?string $disk = null): string
    {
        $diskName = $disk ?: config('filesystems.default', 'local');
        return $file->store($dir, $diskName);
    }

    /**
     * Simpan file unggahan dengan nama spesifik ke disk aktif.
     */
    public static function storeAs(UploadedFile $file, string $dir, string $name, ?string $disk = null): string
    {
        $diskName = $disk ?: config('filesystems.default', 'local');
        return $file->storeAs($dir, $name, $diskName);
    }

    /**
     * Simpan gambar terkonversi WebP ke disk aktif (mendukung lokal dan cloud S3/R2/MinIO).
     */
    public static function storeWebp(UploadedFile $file, string $dir, ?string $disk = null): string
    {
        return ImageStorage::storeWebp($file, $dir, $disk);
    }

    /**
     * Deteksi disk mana yang menyimpan file:
     * 1. Cek disk default (misal 's3').
     * 2. Fallback cek disk 'local' (untuk data yang ada sebelum migrasi cloud).
     * 3. Fallback cek disk 's3' (jika default 'local' tapi file ada di cloud).
     */
    public static function resolveDiskForFile(string $path): ?string
    {
        $defaultDisk = config('filesystems.default', 'local');
        if (Storage::disk($defaultDisk)->exists($path)) {
            return $defaultDisk;
        }

        if ($defaultDisk !== 'local' && Storage::disk('local')->exists($path)) {
            return 'local';
        }

        if ($defaultDisk !== 's3' && config('filesystems.disks.s3.bucket') && Storage::disk('s3')->exists($path)) {
            return 's3';
        }

        return null;
    }

    /**
     * Cek apakah sebuah disk merupakan Object / Bucket Storage (S3, MinIO, Cloudflare R2).
     */
    public static function isBucketDisk(string $diskName): bool
    {
        $driver = config("filesystems.disks.{$diskName}.driver", $diskName);
        return in_array($driver, ['s3'], true) || in_array($diskName, ['s3', 'r2', 'minio'], true);
    }

    /**
     * Sajikan file media dengan strategi Presigned URL untuk Cloud / Bucket
     * dan Streaming Response aman untuk Local Storage:
     * - Jika disk merupakan Bucket Storage (S3, MinIO, Cloudflare R2): redirect 302 ke Presigned Temporary URL.
     * - Jika disk lokal atau adapter tanpa temporary URL: stream langsung dengan Content-Disposition yang tepat.
     */
    public static function response(
        string $path,
        bool $isDownload = false,
        int $expiryMinutes = 15
    ): Response {
        $diskName = self::resolveDiskForFile($path);
        abort_unless($diskName !== null, 404, 'File tidak ditemukan.');

        $disk = Storage::disk($diskName);
        $filename = basename($path);

        // Strategi Presigned URL untuk Cloud / Bucket Storage (S3, MinIO, R2)
        if (self::isBucketDisk($diskName) && method_exists($disk, 'providesTemporaryUrls') && $disk->providesTemporaryUrls()) {
            $options = [];
            if ($isDownload) {
                $options['ResponseContentDisposition'] = 'attachment; filename="' . $filename . '"';
            } else {
                $options['ResponseContentDisposition'] = 'inline; filename="' . $filename . '"';
            }

            $temporaryUrl = $disk->temporaryUrl(
                $path,
                now()->addMinutes($expiryMinutes),
                $options
            );

            if (request()->wantsJson() && request()->boolean('json')) {
                return response()->json([
                    'url' => $temporaryUrl,
                    'expires_at' => now()->addMinutes($expiryMinutes)->toIso8601String(),
                ]);
            }

            return redirect()->away($temporaryUrl);
        }

        // Penyajian untuk disk lokal menggunakan BinaryFileResponse
        // Memberikan dukungan native HTTP Range requests (HTTP 206 Partial Content) untuk Safari/iOS inline PDF
        if ($diskName === 'local') {
            $headers = [
                'Content-Disposition' => ($isDownload ? 'attachment' : 'inline') . '; filename="' . $filename . '"',
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ];

            return $isDownload
                ? response()->download($disk->path($path), $filename, $headers)
                : response()->file($disk->path($path), $headers);
        }

        // Streaming response untuk adapter remote non-bucket tanpa temporary URL
        $stream = $disk->readStream($path);
        $mimeType = $disk->mimeType($path) ?: 'application/octet-stream';
        $size = $disk->size($path);

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => ($isDownload ? 'attachment' : 'inline') . '; filename="' . $filename . '"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        if ($size !== null && $size > 0) {
            $headers['Content-Length'] = (string) $size;
        }

        return response()->stream(function () use ($stream) {
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, $headers);
    }

    /**
     * Hasilkan Presigned Temporary URL secara eksplisit jika didukung driver.
     */
    public static function temporaryUrl(string $path, int $expiryMinutes = 15, array $options = []): ?string
    {
        $diskName = self::resolveDiskForFile($path);
        if (! $diskName) {
            return null;
        }

        $disk = Storage::disk($diskName);
        if (method_exists($disk, 'providesTemporaryUrls') && $disk->providesTemporaryUrls()) {
            return $disk->temporaryUrl($path, now()->addMinutes($expiryMinutes), $options);
        }

        return null;
    }

    /**
     * Hapus file dari storage.
     */
    public static function delete(string $path, ?string $disk = null): bool
    {
        $targetDisk = $disk ?: self::resolveDiskForFile($path) ?: config('filesystems.default', 'local');
        return Storage::disk($targetDisk)->delete($path);
    }
}
