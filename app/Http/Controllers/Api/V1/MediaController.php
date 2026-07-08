<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    /** Folder yang boleh diakses publik (tanpa login). */
    private array $publicFolders = ['schools', 'articles', 'landing', 'settings']; // ← tambah 'settings'

    /** Folder sensitif (khusus staf login). */
    private array $protectedFolders = ['attendances', 'sessions', 'signatures', 'school_notes', 'payments', 'settlements'];

    /** Terproteksi (auth:sanctum) — foto anak/sesi/TTD. */
    public function show(string $path)
    {
        return $this->stream($path, $this->protectedFolders);
    }

    /** Publik — QRIS sekolah, cover artikel, gambar landing. */
    public function publicShow(string $path)
    {
        return $this->stream($path, $this->publicFolders);
    }

    private function stream(string $path, array $allowed)
    {
        $folder = strtok($path, '/');
        abort_if(str_contains($path, '..') || ! in_array($folder, $allowed, true), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path)); // inline + mime otomatis
    }
}
