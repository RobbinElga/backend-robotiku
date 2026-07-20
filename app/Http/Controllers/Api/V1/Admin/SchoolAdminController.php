<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SchoolAdminController extends Controller
{
    /** Envelope sukses { status, data, message }. */
    private function ok($data = null, string $message = 'OK', int $code = 200): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $data, 'message' => $message], $code);
    }

    /** Envelope gagal { status, data, message }. */
    private function fail(string $message, int $code = 422): JsonResponse
    {
        return response()->json(['status' => false, 'data' => null, 'message' => $message], $code);
    }

    /** True bila sekolah dianggap sudah MoU (mengikuti kriteria dropdown /sekolah/mou). */
    private function isMou(School $school): bool
    {
        return (bool) $school->is_mou || $school->pipeline_status === 'sudah_mou';
    }

    /** Sekolah ber-MoU (untuk dropdown pilih sekolah). */
    public function mouSchools(): JsonResponse
    {
        $schools = School::query()
            ->where(fn($q) => $q->where('is_mou', true)->orWhere('pipeline_status', 'sudah_mou'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return $this->ok($schools, 'Sekolah MoU.');
    }

    public function index(Request $request): JsonResponse
    {
        $items = SchoolAdmin::query()
            ->with('school:id,name')
            ->when($request->filled('search'), function ($x) use ($request) {
                $s = $request->search;
                $x->where(fn($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"));
            })
            ->when($request->filled('status'), fn($x) => $x->where('is_active', $request->status === 'aktif'))
            ->when($request->filled('school_id'), fn($x) => $x->where('school_id', $request->school_id))
            ->orderByDesc('created_at')
            ->paginate(10);

        return $this->ok($items, 'Daftar akun admin sekolah.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'max:255', 'unique:school_admins,email'],
            'phone'     => ['required', 'string', 'max:20'],
            'password'  => ['required', 'string', 'min:8'],
        ]);

        $school = School::findOrFail($data['school_id']);
        if (! $this->isMou($school)) {
            return $this->fail('Sekolah belum berstatus MoU, tidak bisa dibuatkan akun.');
        }

        $data['phone'] = Phone::normalize($data['phone']);
        if (SchoolAdmin::where('phone', $data['phone'])->exists()) {
            return $this->fail('Nomor HP sudah dipakai akun lain.');
        }

        $admin = SchoolAdmin::create([
            'school_id' => $school->id,
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'],
            'password'  => Hash::make($data['password']),
            'is_active' => true,
        ]);

        return $this->ok($admin->load('school:id,name'), 'Akun admin sekolah berhasil dibuat.');
    }

    public function update(Request $request, SchoolAdmin $schoolAdmin): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'max:255', Rule::unique('school_admins', 'email')->ignore($schoolAdmin->id)],
            'phone'     => ['required', 'string', 'max:20'],
        ]);

        $school = School::findOrFail($data['school_id']);
        if (! $this->isMou($school)) {
            return $this->fail('Sekolah belum berstatus MoU.');
        }

        $data['phone'] = Phone::normalize($data['phone']);
        if (SchoolAdmin::where('phone', $data['phone'])->where('id', '!=', $schoolAdmin->id)->exists()) {
            return $this->fail('Nomor HP sudah dipakai akun lain.');
        }

        $schoolAdmin->update($data);
        return $this->ok($schoolAdmin->fresh('school:id,name'), 'Akun diperbarui.');
    }

    public function resetPassword(Request $request, SchoolAdmin $schoolAdmin): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:8']]);
        $schoolAdmin->update(['password' => Hash::make($data['password'])]);
        $schoolAdmin->tokens()->delete(); // paksa login ulang dengan password baru
        return $this->ok(null, 'Password akun berhasil direset.');
    }

    public function toggleActive(SchoolAdmin $schoolAdmin): JsonResponse
    {
        $schoolAdmin->update(['is_active' => ! $schoolAdmin->is_active]);
        if (! $schoolAdmin->is_active) {
            $schoolAdmin->tokens()->delete(); // cabut sesi saat dinonaktifkan
        }
        return $this->ok(
            ['is_active' => $schoolAdmin->is_active],
            $schoolAdmin->is_active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.'
        );
    }
}
