<?php

namespace App\Http\Controllers\Api\V1\Keuangan;

use App\Http\Controllers\Controller;
use App\Services\KeuanganDashboardService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KeuanganDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(private KeuanganDashboardService $dashboard) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['nullable', 'string', 'in:bulan_ini,3_bulan,tahun_ini,minggu_ini'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        return $this->success(
            $this->dashboard->dashboard($data['period'] ?? null, $data['start_date'] ?? null, $data['end_date'] ?? null),
            'Dashboard keuangan.'
        );
    }

    public function trend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['nullable', 'string', 'in:bulan_ini,3_bulan,tahun_ini,minggu_ini'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        return $this->success(
            $this->dashboard->trend($data['period'] ?? null, $data['start_date'] ?? null, $data['end_date'] ?? null),
            'Tren keuangan.'
        );
    }

    public function kpi(): JsonResponse
    {
        return $this->success($this->dashboard->dashboard()['pendapatan_bulan_ini'] ?? [], 'KPI keuangan.');
    }
}
