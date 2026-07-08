<?php

namespace App\Http\Controllers\Api\V1\Sekolah;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\SchoolAdmin;
use App\Models\Student;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\ImageStorage;

class SchoolPortalController extends Controller
{
    use ApiResponse;

    private function adminOr403(Request $r): ?SchoolAdmin
    {
        $a = $r->user();
        return $a instanceof SchoolAdmin ? $a : null;
    }

    public function kpi(Request $r): JsonResponse
    {
        $sid = $r->user()->school_id;

        $byStatus = Student::where('school_id', $sid)->verified()
            ->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        $inv = Invoice::whereHas('student', fn($q) => $q->where('school_id', $sid))
            ->selectRaw('status, count(*) c, sum(total_amount) amt')->groupBy('status')->get()->keyBy('status');

        $perProgram = Student::where('students.school_id', $sid)->where('students.status', 'aktif')->where('students.is_verified', true)
            ->join('programs', 'programs.id', '=', 'students.program_id')
            ->selectRaw('programs.name, count(*) c')->groupBy('programs.name')->get()
            ->map(fn($x) => ['name' => $x->name, 'jumlah' => (int) $x->c]);

        $daftar = Student::where('school_id', $sid)->verified()->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at,'%Y-%m') ym, count(*) c")->groupBy('ym')->orderBy('ym')->get()
            ->map(fn($d) => ['bulan' => $d->ym, 'jumlah' => (int) $d->c]);

        return $this->success([
            'kpi' => [
                'total'               => (int) $byStatus->sum(),
                'aktif'               => (int) ($byStatus['aktif'] ?? 0),
                'belum_bayar_nominal' => (int) ($inv['belum_bayar']->amt ?? 0),
                'lunas_nominal'       => (int) ($inv['lunas']->amt ?? 0),
            ],
            'status_murid' => [
                ['name' => 'Aktif', 'value' => (int) ($byStatus['aktif'] ?? 0)],
                ['name' => 'Nonaktif', 'value' => (int) ($byStatus['nonaktif'] ?? 0)],
                ['name' => 'Cuti', 'value' => (int) ($byStatus['cuti'] ?? 0)],
                ['name' => 'Lulus', 'value' => (int) ($byStatus['lulus'] ?? 0)],
            ],
            'tagihan' => [
                ['name' => 'Belum Bayar', 'value' => (int) ($inv['belum_bayar']->c ?? 0)],
                ['name' => 'Menunggu', 'value' => (int) ($inv['menunggu_verifikasi']->c ?? 0)],
                ['name' => 'Lunas', 'value' => (int) ($inv['lunas']->c ?? 0)],
            ],
            'per_program' => $perProgram,
            'pendaftaran' => $daftar,
        ], 'Dashboard sekolah.');
    }

    public function students(Request $request): JsonResponse
    {
        $admin = $this->adminOr403($request);
        if (! $admin) return $this->error('Khusus Admin Sekolah.', 403);

        $students = Student::where('school_id', $admin->school_id)->verified()   // ← hanya siswa terverifikasi
            ->with('classes:id,name')
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return $this->success($students, 'Daftar siswa sekolah.');
    }

    public function rekening(Request $request): JsonResponse
    {
        $s = \App\Models\School::findOrFail($request->user()->school_id);
        return $this->success([
            'bank_account' => $s->bank_account,
            'qris_path'    => $s->qris_image,
            'qris_url'     => $s->qris_image ? asset('storage/' . $s->qris_image) : null,
        ], 'Rekening sekolah.');
    }

    public function updateRekening(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bank_account' => ['nullable', 'string', 'max:150'],
            'qris_image'   => ['nullable', 'string', 'max:255'],
        ]);
        \App\Models\School::whereKey($request->user()->school_id)->update($data);
        return $this->success(null, 'Rekening diperbarui.');
    }

    public function uploadQris(Request $request): JsonResponse
    {
        $request->validate(['image' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120']]);
        $path = ImageStorage::storeWebp($request->file('image'), 'schools');
        return $this->success(['path' => $path, 'url' => asset('storage/' . $path)], 'QRIS terunggah.');
    }

    public function showStudent(Request $request, Student $student): JsonResponse
    {
        $admin = $this->adminOr403($request);
        if (! $admin) return $this->error('Khusus Admin Sekolah.', 403);
        if ($student->school_id !== $admin->school_id) return $this->error('Murid bukan dari sekolah Anda.', 403);

        $student->load(['parent:id,name,phone,greeting', 'program:id,name', 'classes:id,name']);

        return $this->success($student, 'Detail murid.');
    }
}
