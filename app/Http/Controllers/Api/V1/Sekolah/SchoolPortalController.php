<?php

namespace App\Http\Controllers\Api\V1\Sekolah;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\SchoolAdmin;
use App\Models\Student;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolPortalController extends Controller
{
    use ApiResponse;

    private function adminOr403(Request $r): ?SchoolAdmin
    {
        $a = $r->user();
        return $a instanceof SchoolAdmin ? $a : null;
    }

    public function kpi(Request $request): JsonResponse
    {
        $admin = $this->adminOr403($request);
        if (! $admin) return $this->error('Khusus Admin Sekolah.', 403);

        $sid = $admin->school_id;
        $students = Student::where('school_id', $sid);
        $invoices = Invoice::whereHas('student', fn($q) => $q->where('school_id', $sid));

        return $this->success([
            'total_siswa'         => (clone $students)->count(),
            'siswa_aktif'         => (clone $students)->where('status', 'aktif')->count(),
            'tagihan_belum_bayar' => (clone $invoices)->where('status', 'belum_bayar')->count(),
            'total_tagihan_aktif' => (float) (clone $invoices)->whereIn('status', ['belum_bayar', 'menunggu_verifikasi'])->sum('total_amount'),
        ], 'KPI sekolah.');
    }

    public function students(Request $request): JsonResponse
    {
        $admin = $this->adminOr403($request);
        if (! $admin) return $this->error('Khusus Admin Sekolah.', 403);

        $students = Student::where('school_id', $admin->school_id)
            ->with('classes:id,name')
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return $this->success($students, 'Daftar siswa sekolah.');
    }
}
