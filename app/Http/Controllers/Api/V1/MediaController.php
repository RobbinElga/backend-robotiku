<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\MediaStorage;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    /** Folder yang boleh diakses publik (tanpa login). */
    private array $publicFolders = ['schools', 'articles', 'landing', 'settings'];

    /** Folder media internal (termasuk bukti pembayaran orang tua). */
    private array $protectedFolders = ['attendances', 'sessions', 'signatures', 'school_notes', 'payments', 'settlements', 'seeder'];

    /** Folder sensitif staf internal yang WAJIB login Sanctum. */
    private array $staffOnlyFolders = ['attendances', 'sessions', 'signatures', 'school_notes', 'settlements'];

    /** Streaming/Presigned URL media (folder sensitif wajib auth:sanctum; payments diizinkan untuk verifikasi/portal ortu). */
    public function show(string $path): Response
    {
        $folder = strtok($path, '/');
        if (in_array($folder, $this->staffOnlyFolders, true)) {
            abort_unless(auth('sanctum')->check(), 401, 'Unauthenticated.');
        }

        return $this->serve($path, $this->protectedFolders);
    }

    /** Publik — QRIS sekolah, cover artikel, gambar landing, panduan ukuran kaos. */
    public function publicShow(string $path): Response
    {
        return $this->serve($path, $this->publicFolders);
    }

    private function serve(string $path, array $allowed): Response
    {
        $folder = strtok($path, '/');
        abort_if(str_contains($path, '..') || ! in_array($folder, $allowed, true), 404);

        return MediaStorage::response($path, request()->boolean('download'));
    }
}
