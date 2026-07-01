<?php

namespace App\Http\Controllers\Api\V1\Murid;

use App\Http\Controllers\Controller;
use App\Http\Requests\Murid\AbsensiRequest;
use App\Models\Attendance;
use App\Models\Kelas;
use App\Models\Student;
use App\Services\BillingCycleService;
use App\Support\ImageStorage;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use ApiResponse;

    public function __construct(private BillingCycleService $billing) {}

    /** Daftar murid di kelas-kelas milik trainer (isolasi data). */
    public function students(Request $request): JsonResponse
    {
        $trainerId = $request->user()->id;

        $students = Student::whereHas('classes', fn($q) => $q->where('classes.trainer_id', $trainerId))
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->when($request->filled('class_id'), fn($q) =>
            $q->whereHas('classes', fn($c) => $c->where('classes.id', $request->class_id)))
            ->select('id', 'student_code', 'name', 'status')
            ->orderBy('name')
            ->get();

        return $this->success($students, 'Daftar murid.');
    }

    /** Simpan absensi 1 murid. Trainer wajib pemilik kelas & murid ada di kelas itu. */
    public function store(AbsensiRequest $request): JsonResponse
    {
        $trainer = $request->user();

        $class = Kelas::where('id', $request->class_id)
            ->where('trainer_id', $trainer->id)
            ->first();
        if (! $class) {
            return $this->error('Kelas ini bukan kelas Anda.', 403);
        }

        $inClass = $class->students()->where('students.id', $request->student_id)->exists();
        if (! $inClass) {
            return $this->error('Murid tidak terdaftar di kelas ini.', 422);
        }

        $photoPath = $request->hasFile('photo')
            ? ImageStorage::storeWebp($request->file('photo'), 'attendances')
            : null;

        $attendance = Attendance::create([
            'class_id'    => $class->id,
            'student_id'  => $request->student_id,
            'trainer_id'  => $trainer->id,
            'photo'       => $photoPath,
            'status'      => $request->status,
            'report'      => $request->report,
            'attended_at' => now(),
        ]);

        // Trigger siklus tagihan saat "hadir"
        $newInvoice = null;
        if ($request->status === 'hadir') {
            $student = Student::find($request->student_id);
            $newInvoice = $this->billing->handleAttendance($student);
        }

        return $this->success([
            'attendance_id' => $attendance->id,
            'new_invoice'   => $newInvoice?->invoice_number, // null kalau belum kelipatan 4
        ], 'Absensi tersimpan.', 201);
    }

    public function classes(Request $request): JsonResponse
    {
        $classes = Kelas::where('trainer_id', $request->user()->id)
            ->with('students:id,student_code,name,status')
            ->orderBy('name')
            ->get(['id', 'name', 'schedule']);

        return $this->success($classes, 'Kelas Anda.');
    }
}
