<?php

namespace App\Http\Controllers\Api\V1\Murid;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Kelas;
use App\Models\Session;
use App\Models\Setting;
use App\Models\Student;
use App\Services\BillingCycleService;
use App\Services\WhatsappService;
use App\Support\ImageStorage;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Period;

class SessionController extends Controller
{
    use ApiResponse;

    public function __construct(private WhatsappService $wa, private BillingCycleService $billing) {}

    public function myClasses(Request $request): JsonResponse
    {
        $tid = $request->user()->id;
        $classes = Kelas::query()
            ->where(fn($q) => $q->whereHas('trainers', fn($t) => $t->where('users.id', $tid))->orWhere('trainer_id', $tid))
            ->with('program:id,name', 'school:id,name')
            ->withCount(['students as active_count' => fn($q) => $q->where('students.status', 'aktif')])
            ->orderBy('name')->get();

        $classes->each(function ($k) {
            $s = Session::where('class_id', $k->id)->whereDate('started_at', today())->latest()->first();
            $k->setAttribute('today_session', $s ? ['id' => $s->id, 'status' => $s->status, 'started_at' => $s->started_at] : null);
        });
        return $this->success($classes, 'Kelas Anda.');
    }

    /** Mulai Sesi: GPS + selfie → session started → WA "dimulai" ke semua ortu. */
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
            return $this->error('Sesi hari ini sudah dimulai.', 422);
        }
        [$cLat, $cLng, $r] = $this->resolveGeofence($kelas);
        if ($cLat !== null && $this->haversine($cLat, $cLng, (float) $data['latitude'], (float) $data['longitude']) > $r) {
            return $this->error('Anda di luar radius lokasi kelas ini.', 422);
        }

        // Pekan ke-berapa dalam periode, mengikuti meetings_per_period kelas (display saja).
        $perPeriod = max(1, (int) ($kelas->meetings_per_period ?: 4));
        $done = Session::where('class_id', $kelas->id)->count();
        $week = ($done % $perPeriod) + 1;

        $session = Session::create([
            'class_id'        => $kelas->id,
            'period_id'       => null,       // fitur Periode dilepas — tak dipakai lagi
            'week'            => $week,
            'trainer_id'      => $request->user()->id,
            'start_latitude'  => $data['latitude'],
            'start_longitude' => $data['longitude'],
            'start_photo'     => ImageStorage::storeWebp($request->file('photo'), 'sessions'),
            'started_at'      => now(),
            'status'          => 'started',
        ]);
        $this->blast($kelas, 'wa_tpl_session_start');
        return $this->success($session, 'Sesi dimulai.', 201);
    }

    public function students(Session $session): JsonResponse
    {
        abort_unless($session->trainer_id === request()->user()->id, 403, 'Bukan sesi Anda.');
        $students = $session->kelas->students()->where('students.status', 'aktif')->orderBy('name')->get(['students.id', 'student_code', 'name']);
        $att = $session->attendances()->get(['student_id', 'status', 'score', 'report', 'photo'])->keyBy('student_id');

        $data = $students->map(fn($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'student_code' => $s->student_code,
            'status' => $att[$s->id]->status ?? null,
            'score' => $att[$s->id]->score ?? null,
            'report' => $att[$s->id]->report ?? null,
            'photo' => $att[$s->id]->photo ?? null,
        ]);
        return $this->success(['session' => ['id' => $session->id, 'status' => $session->status], 'students' => $data], 'Murid sesi.');
    }

    /** Absensi 1 murid: status + foto + nilai + laporan → WA per status + billing/SPP. */
    /** Absensi 1 murid: status + foto + nilai + laporan → WA per status + billing/SPP. */
    public function attend(Request $request, Session $session): JsonResponse
    {
        abort_unless($session->trainer_id === $request->user()->id, 403, 'Bukan sesi Anda.');
        if ($session->status === 'ended') return $this->error('Sesi sudah selesai.', 422);

        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'status'     => ['required', 'in:hadir,izin,sakit,tanpa_keterangan'],
            'score'      => ['nullable', 'in:A,B,C,D,E'],
            'report'     => ['nullable', 'string'],
            'photo'      => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);
        $student = Student::with('parent')->findOrFail($data['student_id']);
        if ($student->status !== 'aktif') return $this->error('Murid tidak aktif.', 422);
        if (! $session->kelas->students()->where('students.id', $student->id)->exists()) return $this->error('Murid tidak di kelas ini.', 422);

        $existing  = Attendance::where('session_id', $session->id)->where('student_id', $student->id)->first();
        $isNew     = ! $existing;
        $oldStatus = $existing?->status;

        $att = $existing ?? new Attendance();
        $att->fill([
            'session_id'  => $session->id,
            'class_id'    => $session->class_id,
            'student_id'  => $student->id,
            'trainer_id'  => $session->trainer_id,
            'status'      => $data['status'],
            'score'       => $data['score'] ?? null,
            'report'      => $data['report'] ?? null,
            'attended_at' => now(),
        ]);
        if ($request->hasFile('photo')) $att->photo = ImageStorage::storeWebp($request->file('photo'), 'attendances');
        $att->save();

        // WA notifikasi status kehadiran ke ortu — dikirim saat baru ATAU status berubah (hindari spam saat edit lain)
        if ($isNew || $oldStatus !== $data['status']) {
            $this->notifyStatus($student, $data['status']);
        }

        // Billing per-murid saat hadir (buat tagihan periode berikutnya bila batas periode tercapai)
        if ($data['status'] === 'hadir') {
            $invoice = $this->billing->onAttendance($student, $session->kelas);
            if ($invoice) $this->notifySpp($student);
        }

        return $this->success(['attendance_id' => $att->id], 'Absensi tersimpan.', $isNew ? 201 : 200);
    }

    /** Selesai Sesi: GPS + foto → WA "selesai" (nama+nomor trainer). */
    public function end(Request $request, Session $session): JsonResponse
    {
        abort_unless($session->trainer_id === $request->user()->id, 403, 'Bukan sesi Anda.');
        if ($session->status === 'ended') return $this->error('Sesi sudah selesai.', 422);
        $data = $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);
        $session->update([
            'end_latitude' => $data['latitude'],
            'end_longitude' => $data['longitude'],
            'end_photo' => ImageStorage::storeWebp($request->file('photo'), 'sessions'),
            'ended_at' => now(),
            'status' => 'ended',
        ]);
        $trainer = $request->user();
        $this->blast($session->kelas, 'wa_tpl_session_end', ['nama_trainer' => $trainer->name, 'nomor_trainer' => $trainer->phone ?? '-']);
        return $this->success($session->fresh(), 'Sesi selesai.');
    }

    /* helpers */
    private function isTrainerOf(Kelas $k, int $tid): bool
    {
        return $k->trainer_id === $tid || $k->trainers()->where('users.id', $tid)->exists();
    }
    private function blast(Kelas $k, string $tpl, array $extra = []): void
    {
        foreach ($k->students()->where('students.status', 'aktif')->with('parent')->get() as $st) {
            if (! $st->parent?->phone) continue;
            $this->wa->sendTemplate($tpl, $st->parent->phone, array_merge(['sapaan' => $this->sapaan($st->parent?->greeting), 'nama_anak' => $st->name], $extra));
        }
    }
    private function notifyStatus(Student $st, string $status): void
    {
        $map = ['hadir' => 'wa_tpl_hadir', 'izin' => 'wa_tpl_izin', 'sakit' => 'wa_tpl_sakit', 'tanpa_keterangan' => 'wa_tpl_alpha'];
        if (! $st->parent?->phone || ! isset($map[$status])) return;
        $this->wa->sendTemplate($map[$status], $st->parent->phone, ['sapaan' => $this->sapaan($st->parent?->greeting), 'nama_anak' => $st->name]);
    }
    private function notifySpp(Student $st): void
    {
        if (! $st->parent?->phone) return;
        $this->wa->sendTemplate('wa_tpl_spp_reminder', $st->parent->phone, ['sapaan' => $this->sapaan($st->parent?->greeting), 'nama_anak' => $st->name]);
    }
    private function sapaan(?string $g): string
    {
        return match ($g) {
            'ayah' => 'Ayah',
            'bunda' => 'Bunda',
            default => 'Ayah/Bunda'
        };
    }
    private function resolveGeofence(Kelas $k): array
    {
        if ($k->school_id) {
            $k->loadMissing('school');
            $s = $k->school;
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

    /** Riwayat/rekap sesi. Trainer → miliknya; Admin/Super → semua. */
    public function rekap(Request $request): JsonResponse
    {
        $u = $request->user();
        $q = Session::with(['kelas:id,name', 'trainer:id,name'])
            ->withCount([
                'attendances as hadir' => fn($x) => $x->where('status', 'hadir'),
                'attendances as izin' => fn($x) => $x->where('status', 'izin'),
                'attendances as sakit' => fn($x) => $x->where('status', 'sakit'),
                'attendances as alpa' => fn($x) => $x->where('status', 'tanpa_keterangan'),
            ])
            ->when($u->role === 'trainer', fn($x) => $x->where('trainer_id', $u->id))
            ->when($request->filled('date_from'), fn($x) => $x->whereDate('started_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($x) => $x->whereDate('started_at', '<=', $request->date_to))
            ->when($request->filled('class_id'), fn($x) => $x->where('class_id', $request->class_id))
            ->latest('started_at')
            ->paginate($request->integer('per_page', 20));

        return $this->success($q, 'Rekap sesi.');
    }

    /** Detail sesi (read-only) untuk rekap. */
    public function show(Session $session): JsonResponse
    {
        $u = request()->user();
        abort_if($u->role === 'trainer' && $session->trainer_id !== $u->id, 403, 'Akses ditolak.');
        $session->load(['kelas:id,name', 'trainer:id,name']);

        $rows = $session->attendances()->with('student:id,name,student_code')->get()->map(fn($a) => [
            'id' => $a->student_id,
            'name' => $a->student->name ?? '-',
            'student_code' => $a->student->student_code ?? '',
            'status' => $a->status,
            'score' => $a->score,
            'report' => $a->report,
            'photo' => $a->photo,
        ]);

        return $this->success([
            'session' => [
                'id' => $session->id,
                'status' => $session->status,
                'started_at' => $session->started_at,
                'ended_at' => $session->ended_at,
                'kelas' => $session->kelas?->name,
                'trainer' => $session->trainer?->name,
                'start_photo' => $session->start_photo,
                'end_photo' => $session->end_photo,
                'start_lat' => $session->start_latitude,
                'start_lng' => $session->start_longitude,
            ],
            'attendances' => $rows,
        ], 'Detail sesi.');
    }



    public function periods(\App\Models\Kelas $kelas): JsonResponse
    {
        $q = Period::where('is_active', true);
        $kelas->school_id
            ? $q->where('scope', 'sekolah')->where('school_id', $kelas->school_id)
            : $q->where('scope', 'mandiri');
        return $this->success($q->orderBy('number')->get(['id', 'name', 'number']), 'Periode kelas.');
    }
}
