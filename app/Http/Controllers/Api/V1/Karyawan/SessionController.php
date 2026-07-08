<?php

namespace App\Http\Controllers\Api\V1\Karyawan;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Session;
use App\Models\Setting;
use App\Support\ImageStorage;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    use ApiResponse;

    /** Kelas milik trainer + status presensi hari ini. */
    public function myClasses(Request $request): JsonResponse
    {
        $tid = $request->user()->id;
        $classes = Kelas::query()
            ->where(fn($q) => $q->whereHas('trainers', fn($t) => $t->where('users.id', $tid))->orWhere('trainer_id', $tid))
            ->with('program:id,name', 'school:id,name')
            ->orderBy('name')->get();

        $classes->each(function ($k) {
            $s = Session::where('class_id', $k->id)->whereDate('started_at', today())->latest()->first();
            $k->setAttribute('today_session', $s ? ['id' => $s->id, 'status' => $s->status, 'started_at' => $s->started_at, 'ended_at' => $s->ended_at] : null);
        });

        return $this->success($classes, 'Kelas Anda.');
    }

    /** Mulai presensi: GPS + selfie wajib, radius sesuai lokasi kelas (sekolah/kantor). */
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'class_id'  => ['required', 'exists:classes,id'],
            'latitude'  => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'photo'     => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        $kelas = Kelas::findOrFail($data['class_id']);
        abort_unless($this->isTrainerOf($kelas, $request->user()->id), 403, 'Kelas ini bukan kelas Anda.');

        if (Session::where('class_id', $kelas->id)->whereDate('started_at', today())->where('status', 'started')->exists()) {
            return $this->error('Presensi sesi ini sudah dimulai.', 422);
        }

        [$cLat, $cLng, $radius] = $this->resolveGeofence($kelas);
        if ($cLat !== null && $this->haversine($cLat, $cLng, (float) $data['latitude'], (float) $data['longitude']) > $radius) {
            return $this->error('Anda di luar radius lokasi kelas ini.', 422);
        }

        $photo = ImageStorage::storeWebp($request->file('photo'), 'sessions');
        $session = Session::create([
            'class_id' => $kelas->id,
            'trainer_id' => $request->user()->id,
            'start_latitude' => $data['latitude'],
            'start_longitude' => $data['longitude'],
            'start_photo' => $photo,
            'started_at' => now(),
            'status' => 'started',
        ]);

        return $this->success($session, 'Presensi dimulai.', 201);
    }

    /** Selesai presensi (tanpa GPS lagi). */
    public function end(Request $request, Session $session): JsonResponse
    {
        abort_unless($session->trainer_id === $request->user()->id, 403, 'Bukan sesi Anda.');
        if ($session->status === 'ended') return $this->error('Sesi sudah selesai.', 422);
        $session->update(['ended_at' => now(), 'status' => 'ended']);
        return $this->success($session->fresh(), 'Presensi selesai.');
    }

    /** Rekap (Admin/Super Admin). */
    public function rekap(Request $request): JsonResponse
    {
        $rows = Session::with(['trainer:id,name', 'kelas:id,name'])
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('started_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('started_at', '<=', $request->date_to))
            ->latest('started_at')
            ->paginate($request->integer('per_page', 20));

        return $this->success($rows, 'Rekap presensi trainer.');
    }

    /* helpers */
    private function isTrainerOf(Kelas $kelas, int $tid): bool
    {
        return $kelas->trainer_id === $tid || $kelas->trainers()->where('users.id', $tid)->exists();
    }
    private function resolveGeofence(Kelas $kelas): array
    {
        if ($kelas->school_id) {
            $kelas->loadMissing('school');
            $s = $kelas->school;
            if ($s && $s->latitude && $s->longitude) return [(float) $s->latitude, (float) $s->longitude, (int) ($s->geofence_radius ?? 500)];
            return [null, null, 0];
        }
        $lat = Setting::get('office_latitude');
        $lng = Setting::get('office_longitude');
        if ($lat && $lng) return [(float) $lat, (float) $lng, (int) Setting::get('office_radius', 500)];
        return [null, null, 0];
    }
    private function haversine(float $la1, float $lo1, float $la2, float $lo2): float
    {
        $R = 6371000;
        $dLa = deg2rad($la2 - $la1);
        $dLo = deg2rad($lo2 - $lo1);
        $a = sin($dLa / 2) ** 2 + cos(deg2rad($la1)) * cos(deg2rad($la2)) * sin($dLo / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
