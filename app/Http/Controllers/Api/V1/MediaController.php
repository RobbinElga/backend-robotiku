<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    /** Folder yang boleh diakses publik (tanpa login). */
    private array $publicFolders = ['schools', 'articles', 'landing', 'settings']; // ← tambah 'settings'

    /** Folder sensitif (khusus staf login). */
    private array $protectedFolders = ['attendances', 'sessions', 'signatures', 'school_notes', 'payments', 'settlements', 'seeder'];

    /** Terproteksi (auth:sanctum) — foto anak/sesi/TTD. */
    public function show(string $path): BinaryFileResponse
    {
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
