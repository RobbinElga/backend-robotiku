<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Invoice;
use App\Models\School;
use App\Models\Student;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $pipeline = School::selectRaw('pipeline_status, COUNT(*) as total')
            ->groupBy('pipeline_status')
            ->pluck('total', 'pipeline_status');

        return $this->success([
            'siswa_aktif'         => Student::where('status', 'aktif')->count(),
            'siswa_total'         => Student::count(),
            'pendapatan'          => (float) Invoice::where('status', 'lunas')->sum('total_amount'),
            'tagihan_belum_bayar' => Invoice::where('status', 'belum_bayar')->count(),
            'menunggu_verifikasi' => Invoice::where('status', 'menunggu_verifikasi')->count(),
            'sekolah_mou'         => School::where('is_mou', true)->count(),
            'pipeline'            => [
                'prospek'      => (int) ($pipeline['prospek'] ?? 0),
                'dalam_proses' => (int) ($pipeline['dalam_proses'] ?? 0),
                'sudah_mou'    => (int) ($pipeline['sudah_mou'] ?? 0),
                'tidak_lanjut' => (int) ($pipeline['tidak_lanjut'] ?? 0),
            ],
            'kehadiran_bulan_ini' => Attendance::where('status', 'hadir')
                ->whereMonth('attended_at', now()->month)
                ->whereYear('attended_at', now()->year)
                ->count(),
        ], 'KPI dashboard.');
    }
}
