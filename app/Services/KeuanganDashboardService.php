<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolSettlement;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KeuanganDashboardService
{
    public function dashboard(?string $period = null, ?string $startDate = null, ?string $endDate = null): array
    {
        [$start, $end] = $this->resolveDateRange($period, $startDate, $endDate);

        return [
            'pendapatan_bulan_ini' => $this->pendapatan($start, $end),
            'pendapatan_bulan_ini_label' => $this->pendapatanLabel($period, $startDate, $endDate, $start, $end),
            'pendapatan_tahun_ini' => $this->pendapatanTahunIni(),
            'tagihan_outstanding' => $this->tagihanOutstanding(),
            'rata_rata_komisi' => $this->rataRataKomisi(),
            'sekolah_aktif' => $this->sekolahAktif(),
            'menunggu_verifikasi' => $this->menungguVerifikasi(),
            'menunggu_verifikasi_route' => '/admin/keuangan/verifikasi',
            'pendapatan_per_bulan' => $this->pendapatanPerBulan(),
            'status_pembayaran_global' => $this->statusPembayaranGlobal(),
            'tagihan_outstanding_per_sekolah' => $this->tagihanOutstandingPerSekolah(),
            'komisi_per_sekolah' => $this->komisiPerSekolah(),
            'setoran_terbaru' => $this->setoranTerbaru(),
        ];
    }

    public function trend(?string $period = null, ?string $startDate = null, ?string $endDate = null): array
    {
        [$start, $end] = $this->resolveDateRange($period, $startDate, $endDate);
        $days = max($start->diffInDays($end), 1);

        $prevEnd = $start->copy()->subDay()->endOfDay();
        $prevStart = $start->copy()->subDays($days)->startOfDay();

        $current = $this->pendapatan($start, $end);
        $previous = $this->pendapatan($prevStart, $prevEnd);

        return [
            'pendapatan_bulan_ini' => [
                'current' => $current,
                'previous' => $previous,
                'percent_change' => $previous > 0
                    ? round((($current - $previous) / $previous) * 100, 1)
                    : ($current > 0 ? 100.0 : 0.0),
            ],
        ];
    }

    private function resolveDateRange(?string $period, ?string $startDate, ?string $endDate): array
    {
        if ($startDate && $endDate) {
            return [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()];
        }

        $now = now();

        return match ($period) {
            'minggu_ini' => [$now->copy()->startOfWeek(), $now->copy()->endOfDay()],
            '3_bulan' => [$now->copy()->subMonths(3)->startOfMonth(), $now->copy()->endOfDay()],
            'tahun_ini' => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
        };
    }

    private function pendapatanLabel(?string $period, ?string $startDate, ?string $endDate, Carbon $start, Carbon $end): string
    {
        if ($startDate && $endDate) {
            return 'Pendapatan ' . $start->format('d M Y') . ' - ' . $end->format('d M Y');
        }

        return match ($period) {
            'minggu_ini' => 'Pendapatan Minggu Ini',
            '3_bulan' => 'Pendapatan 3 Bulan Terakhir',
            'tahun_ini' => 'Pendapatan Tahun Ini',
            default => 'Pendapatan Bulan Ini',
        };
    }

    private function pendapatan(Carbon $start, Carbon $end): float
    {
        return (float) Invoice::query()
            ->join('payments', 'invoices.id', '=', 'payments.invoice_id')
            ->where('payments.status', 'diverifikasi')
            ->whereBetween('payments.verified_at', [$start, $end])
            ->sum('invoices.total_amount');
    }

    private function pendapatanTahunIni(): float
    {
        return (float) Invoice::query()
            ->join('payments', 'invoices.id', '=', 'payments.invoice_id')
            ->where('payments.status', 'diverifikasi')
            ->whereBetween('payments.verified_at', [now()->startOfYear(), now()->endOfDay()])
            ->sum('invoices.total_amount');
    }

    private function tagihanOutstanding(): float
    {
        return (float) Invoice::where('status', 'belum_bayar')->sum('total_amount');
    }

    private function rataRataKomisi(): float
    {
        return round((float) School::avg('commission_percent') ?? 0, 1);
    }

    private function sekolahAktif(): int
    {
        return Student::where('status', 'aktif')
            ->distinct()
            ->count('school_id');
    }

    private function menungguVerifikasi(): int
    {
        return Payment::where('status', 'menunggu_verifikasi')->count();
    }

    private function pendapatanPerBulan(): array
    {
        $end = now()->endOfMonth();
        $start = now()->subYear()->startOfMonth();

        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', payments.verified_at)"
            : "DATE_FORMAT(payments.verified_at, '%Y-%m')";

        $rows = DB::table('invoices')
            ->join('payments', 'invoices.id', '=', 'payments.invoice_id')
            ->where('payments.status', 'diverifikasi')
            ->whereBetween('payments.verified_at', [$start, $end])
            ->select(DB::raw("{$monthExpr} as month"), DB::raw('SUM(invoices.total_amount) as total'))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $result = [];
        $cursor = $start->copy();

        while ($cursor <= $end) {
            $key = $cursor->format('Y-m');
            $result[] = [
                'month' => $cursor->translatedFormat('M'),
                'total' => isset($rows[$key]) ? (float) $rows[$key]->total : 0,
            ];
            $cursor->addMonth();
        }

        return $result;
    }

    private function statusPembayaranGlobal(): array
    {
        $counts = Invoice::selectRaw('status, COUNT(*) as count')
            ->whereIn('status', ['belum_bayar', 'lunas'])
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'belum_bayar' => $counts['belum_bayar'] ?? 0,
            'lunas' => $counts['lunas'] ?? 0,
        ];
    }

    private function tagihanOutstandingPerSekolah(): array
    {
        return Invoice::selectRaw('schools.name as school_name, SUM(invoices.total_amount) as total')
            ->join('students', 'invoices.student_id', '=', 'students.id')
            ->join('schools', 'students.school_id', '=', 'schools.id')
            ->where('invoices.status', 'belum_bayar')
            ->groupBy('schools.id', 'schools.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function komisiPerSekolah(): array
    {
        return School::query()
            ->select('name as school_name', 'commission_percent')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    private function setoranTerbaru(): array
    {
        return SchoolSettlement::with('school:id,name')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($s) => [
                'school_name' => $s->school->name,
                'net_amount' => (float) $s->net_amount,
                'created_at' => $s->created_at->toIso8601String(),
                'status' => $s->status,
            ])
            ->values()
            ->toArray();
    }
}
