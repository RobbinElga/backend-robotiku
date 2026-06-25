<?php

namespace App\Http\Controllers\Api\V1\Murid;

use App\Http\Controllers\Controller;
use App\Http\Requests\Murid\ParentProgressRequest;
use App\Models\SchoolAdmin;
use App\Models\Student;
use App\Models\User;
use App\Services\ProgressService;
use App\Support\Phone;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    use ApiResponse;

    public function __construct(private ProgressService $progress) {}

    /** Ortu (passwordless, verifikasi via HP). */
    public function parent(ParentProgressRequest $request): JsonResponse
    {
        $student = Student::with('parent')->find($request->student_id);

        if (! $student || optional($student->parent)->phone !== Phone::normalize($request->phone)) {
            return $this->error('Data tidak cocok. Periksa nomor HP.', 403);
        }

        return $this->success($this->progress->forStudent($student), 'Progress anak.');
    }

    /** Admin Sekolah — hanya murid sekolahnya. */
    public function school(Request $request, Student $student): JsonResponse
    {
        $admin = $request->user();
        if (! $admin instanceof SchoolAdmin) {
            return $this->error('Akses ditolak: khusus Admin Sekolah.', 403);
        }
        if ($student->school_id !== $admin->school_id) {
            return $this->error('Murid bukan dari sekolah Anda.', 403);
        }

        return $this->success($this->progress->forStudent($student), 'Progress murid.');
    }

    /** Internal — Trainer (hanya kelasnya), Admin & Super Admin (semua). */
    public function internal(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof User && $user->role === 'trainer') {
            $owns = $student->classes()->where('classes.trainer_id', $user->id)->exists();
            if (! $owns) {
                return $this->error('Murid ini bukan di kelas Anda.', 403);
            }
        }

        return $this->success($this->progress->forStudent($student), 'Progress murid.');
    }
}
