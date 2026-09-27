<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    /** Folder yang boleh diakses publik (tanpa login). */
    private array $publicFolders = ['schools', 'articles', 'landing', 'settings']; // ← tambah 'settings'

    /** Folder media internal (termasuk bukti pembayaran orang tua). */
    private array $protectedFolders = ['attendances', 'sessions', 'signatures', 'school_notes', 'payments', 'settlements', 'seeder'];

    /** Folder sensitif staf internal yang WAJIB login Sanctum. */
    private array $staffOnlyFolders = ['attendances', 'sessions', 'signatures', 'school_notes', 'settlements'];

    /** Streaming media (folder sensitif wajib auth:sanctum; payments diizinkan untuk verifikasi/portal ortu). */
    public function show(string $path): BinaryFileResponse
    {
        $folder = strtok($path, '/');
        if (in_array($folder, $this->staffOnlyFolders, true)) {
            abort_unless(auth('sanctum')->check(), 401, 'Unauthenticated.');
        }

        return $this->stream($path, $this->protectedFolders);
    }

    /** Publik — QRIS sekolah, cover artikel, gambar landing. */
    public function publicShow(string $path): BinaryFileResponse
    {
        return $this->stream($path, $this->publicFolders);
    }

    private function stream(string $path, array $allowed): BinaryFileResponse
    {
        $folder = strtok($path, '/');
        abort_if(str_contains($path, '..') || ! in_array($folder, $allowed, true), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        if (request()->boolean('download')) {
            return response()->download($disk->path($path));
        }

        return response()->file($disk->path($path), [
            'Content-Disposition' => 'inline',
        ]);
    }
}
