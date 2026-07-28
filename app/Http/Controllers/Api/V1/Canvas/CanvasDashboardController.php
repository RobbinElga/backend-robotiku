<?php

namespace App\Http\Controllers\Api\V1\Canvas;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\School;
use App\Models\SchoolNote;
use App\Models\SchoolStatusLog;
use App\Models\Setting;
use App\Traits\ApiResponse;
use Carbon\Carbon;

class CanvasDashboardController extends Controller
{
    use ApiResponse;

    /**
     * TAHAP 2: Analitik Canvas (Super Admin & Admin)
     */
    public function analitik(): JsonResponse
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // 1. Hitung Status Keseluruhan (Sepanjang Waktu)
        $byStatus = DB::table('schools')->selectRaw('pipeline_status, count(*) c')->groupBy('pipeline_status')->pluck('c', 'pipeline_status');
        $total = (int) $byStatus->sum();
        $mou = (int) ($byStatus['sudah_mou'] ?? 0);

        // 2. Kunjungan dan Canvaser Aktif (Bulan Ini)
        $kunjunganBulanIni = DB::table('school_notes')
            ->where('kind', 'pertemuan')
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear);

        $totalKunjunganBulanIni = (int) $kunjunganBulanIni->count();
        $canvaserAktifBulanIni = (int) $kunjunganBulanIni->distinct('created_by')->count('created_by');

        // 3. Papan Peringkat Canvaser (Kunjungan khusus bulan ini)
        $visitByUserBulanIni = DB::table('school_notes')
            ->where('kind', 'pertemuan')
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->selectRaw('created_by, count(*) c')
            ->groupBy('created_by')
            ->pluck('c', 'created_by');

        $perCanvaser = DB::table('schools')
            ->join('users', 'users.id', '=', 'schools.created_by')
            ->selectRaw('users.id, users.name, count(*) total, sum(schools.pipeline_status = "sudah_mou") mou')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn($u) => [
                'name'      => $u->name,
                'total'     => (int) $u->total,
                'mou'       => (int) $u->mou,
                'kunjungan' => (int) ($visitByUserBulanIni[$u->id] ?? 0),
                'konversi'  => $u->total > 0 ? (int) round($u->mou / $u->total * 100) : 0,
            ]);

        // 4. Tren MoU (Tahun Ini vs Tahun Lalu) menggunakan SchoolStatusLog
        $lastYear = $currentYear - 1;
        $mouLogs = DB::table('school_status_logs')
            ->where('new_status', 'sudah_mou')
            ->whereYear('created_at', '>=', $lastYear)
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, count(*) as count')
            ->groupBy('year', 'month')
            ->get();

        $tren = ['tahun_ini' => [], 'tahun_lalu' => []];
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];

        foreach ([$currentYear => 'tahun_ini', $lastYear => 'tahun_lalu'] as $year => $key) {
            $cumulative = 0;
            foreach (range(1, 12) as $m) {
                $monthCount = $mouLogs->where('year', $year)->where('month', $m)->first()->count ?? 0;
                $cumulative += $monthCount; 
                
                $tren[$key][] = [
                    'bulan'  => $months[$m - 1],
                    'jumlah' => $cumulative
                ];
                
                if ($year === $currentYear && $m === $currentMonth) {
                    break;
                }
            }
        }

        return $this->success([
            'kpi' => [
                'total'                    => $total,
                'mou'                      => $mou,
                'konversi'                 => $total > 0 ? (int) round($mou / $total * 100) : 0,
                'kunjungan'                => $totalKunjunganBulanIni,
                'canvaser_aktif'           => $canvaserAktifBulanIni,
                'target_kunjungan_bulanan' => (int) Setting::get('target_kunjungan_bulanan', 20),
            ],
            'status' => [
                ['name' => 'Prospek', 'value' => (int) ($byStatus['prospek'] ?? 0)],
                ['name' => 'Dalam Proses', 'value' => (int) ($byStatus['dalam_proses'] ?? 0)],
                ['name' => 'MoU', 'value' => (int) ($byStatus['sudah_mou'] ?? 0)],
            ],
            'per_canvaser' => $perCanvaser,
            'tren'         => $tren,
        ], 'Analitik Canvas Super Admin.');
    }


    /**
     * TAHAP 3: Pusat Komando Marketing
     */
    public function marketing(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // 1. Cari beban kerja aktif
        $sekolahAktif = School::where('created_by', $userId)
            ->whereIn('pipeline_status', ['prospek', 'dalam_proses'])
            ->addSelect(['last_visit_date' => SchoolNote::select('created_at')
                ->whereColumn('school_id', 'schools.id')
                ->where('kind', 'pertemuan')
                ->latest()
                ->take(1)
            ])->get();

        $prospekAktif = $sekolahAktif->count();

        // 2. Filter yang Menunggu Follow-up (Dalam proses tapi > 7 hari tidak disentuh)
        $menungguFollowUp = $sekolahAktif->filter(function($s) {
            if ($s->pipeline_status !== 'dalam_proses') return false;
            return !$s->last_visit_date || Carbon::parse($s->last_visit_date)->lt(now()->subDays(7));
        });

        // 3. MoU Bulan Ini (menggunakan log status)
        $mouBulanIni = SchoolStatusLog::where('changed_by', $userId)
            ->where('new_status', 'sudah_mou')
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->count();

        // 4. Kunjungan Bulan Ini
        $kunjunganBulanIni = SchoolNote::where('created_by', $userId)
            ->where('kind', 'pertemuan')
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->count();

        // 5. Corong (Status Pipeline pribadi milik user)
        $statusCounts = School::where('created_by', $userId)
            ->selectRaw('pipeline_status, count(*) c')
            ->groupBy('pipeline_status')
            ->pluck('c', 'pipeline_status');

        $status = [
            ['name' => 'Prospek', 'value' => (int) ($statusCounts['prospek'] ?? 0)],
            ['name' => 'Dalam Proses', 'value' => (int) ($statusCounts['dalam_proses'] ?? 0)],
            ['name' => 'MoU', 'value' => (int) ($statusCounts['sudah_mou'] ?? 0)],
        ];

        // 6. Menyusun Seluruh Prioritas Follow-Up (Tanpa Batasan)
        $prioritas = $menungguFollowUp->sortBy('last_visit_date')->values()->map(function($s) {
            $days = $s->last_visit_date ? (int) Carbon::parse($s->last_visit_date)->diffInDays(now()) : null;
            return [
                'id'         => $s->id,
                'name'       => $s->name,
                'status'     => 'Dalam Proses',
                'last_touch' => $days === null ? 'Belum pernah dikunjungi' : "{$days} Hari yang lalu",
            ];
        });

        return $this->success([
            'kpi' => [
                'prospekAktif'      => $prospekAktif,
                'menungguFollowUp'  => $menungguFollowUp->count(),
                'mouBulanIni'       => $mouBulanIni,
                'kunjunganBulanIni' => $kunjunganBulanIni,
                'targetKunjungan'   => (int) Setting::get('target_kunjungan_bulanan', 20),
            ],
            'status'    => $status,
            'prioritas' => $prioritas
        ], 'Dashboard Pusat Komando Marketing.');
    }

    /**
     * Memperbarui Target KPI Bulanan (Super Admin)
     */
    public function updateTarget(Request $request): JsonResponse
    {
        $request->validate([
            'target' => ['required', 'integer', 'min:1']
        ]);

        // Simpan ke tabel settings
        Setting::put('target_kunjungan_bulanan', $request->target, $request->user()->id);

        return $this->success(null, 'Target kunjungan berhasil diperbarui.');
    }
}