<?php

namespace App\Http\Controllers\Api\V1\Karyawan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Karyawan\PresensiRequest;
use App\Models\EmployeeAttendance;
use App\Services\GeofenceService;
use App\Support\ImageStorage;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeAttendanceController extends Controller
{
    use ApiResponse;

    public function __construct(private GeofenceService $geofence) {}

    /** Trainer presensi harian. */
    public function store(PresensiRequest $request): JsonResponse
    {
        $trainer = $request->user();

        if (EmployeeAttendance::where('trainer_id', $trainer->id)
            ->whereDate('attendance_date', today())   // <- ganti ke whereDate
            ->exists()
        ) {
            return $this->error('Anda sudah presensi hari ini.', 422);
        }

        $geo = $this->geofence->check((float) $request->latitude, (float) $request->longitude);
        if (! $geo['within']) {
            return $this->error("Di luar radius lokasi ({$geo['distance']} m dari titik, maks {$geo['radius']} m).", 422);
        }

        $photoPath = ImageStorage::storeWebp($request->file('photo'), 'employee-attendances');

        $attendance = EmployeeAttendance::create([
            'trainer_id'      => $trainer->id,
            'photo'           => $photoPath,
            'latitude'        => $request->latitude,
            'longitude'       => $request->longitude,
            'notes'           => $request->notes,
            'attendance_date' => today(),
        ]);

        return $this->success(['id' => $attendance->id, 'distance' => $geo['distance']], 'Presensi berhasil.', 201);
    }

    public function today(Request $request): JsonResponse
    {
        $exists = EmployeeAttendance::where('trainer_id', $request->user()->id)
            ->whereDate('attendance_date', today())   // <- ganti ke whereDate
            ->exists();

        return $this->success(['sudah_presensi' => $exists], 'Status presensi hari ini.');
    }

    /** Rekap (Admin & Super Admin). */
    public function index(Request $request): JsonResponse
    {
        $rekap = EmployeeAttendance::with('trainer:id,name')
            ->when($request->filled('trainer_id'), fn($q) => $q->where('trainer_id', $request->trainer_id))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('attendance_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('attendance_date', '<=', $request->date_to))
            ->orderByDesc('attendance_date')
            ->paginate($request->integer('per_page', 30));

        return $this->success($rekap, 'Rekap presensi.');
    }

    public function destroy(EmployeeAttendance $employeeAttendance): JsonResponse
    {
        $employeeAttendance->delete();

        return $this->success(null, 'Data presensi dihapus.');
    }
}
