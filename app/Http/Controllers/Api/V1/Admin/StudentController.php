<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentStatusLog;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\StudentsExport;
use App\Http\Requests\Admin\StudentStatusRequest;
use App\Http\Requests\Admin\UpdateStudentBiodataRequest;
use App\Models\SchoolAdmin;
use App\Models\StudentParent;
use App\Services\StudentBiodataService;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly StudentBiodataService $biodataService = new StudentBiodataService(),
    ) {}

    public function index(Request $request): JsonResponse
    {
        $students = $this->filtered($request)
            ->when($request->boolean('unassigned'), fn($q) => $q->whereDoesntHave('classes'))
            ->reorder()
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->success($students, 'Data siswa.');
    }

    public function show(Student $student): JsonResponse
    {
        $student->load([
            'parent:id,name,phone,greeting',
            'school:id,name',
            'classes:id,name',
            'statusLogs' => fn($q) => $q->orderByDesc('created_at'),
        ]);

        return $this->success($student, 'Detail siswa.');
    }

    public function changeStatus(StudentStatusRequest $request, Student $student): JsonResponse
    {
        $newStatus = $request->validated('status');
        if ($newStatus === 'berhenti') {
            $newStatus = 'nonaktif';
        }

        if ($student->status === $newStatus) {
            return $this->error('Status murid sudah ' . $newStatus . '.', 422);
        }

        // "lulus" hanya Super Admin
        if ($newStatus === 'lulus' && $request->user()?->role !== 'super_admin') {
            return $this->error('Status "Lulus" hanya bisa diubah oleh Super Admin.', 403);
        }

        $oldStatus = $student->status;
        $user = $request->user();
        $changedByType = $user instanceof SchoolAdmin ? 'school_admin' : 'user';

        DB::transaction(function () use ($student, $newStatus, $oldStatus, $request, $changedByType, $user) {
            $student->update(['status' => $newStatus]);
            StudentStatusLog::create([
                'student_id'      => $student->id,
                'old_status'      => $oldStatus,
                'new_status'      => $newStatus,
                'note'            => $request->validated('note'),
                'changed_by_type' => $changedByType,
                'changed_by'      => $user?->id,
            ]);
        });

        return $this->success($student->fresh(), 'Status murid diperbarui.');
    }

    public function update(UpdateStudentBiodataRequest $request, Student $student): JsonResponse
    {
        $this->biodataService->update($student, $request->validated());

        $student->load([
            'parent:id,name,phone,greeting,phone_alt',
            'school:id,name',
            'program:id,name',
            'classes:id,name',
        ]);

        return $this->success($student, 'Biodata murid berhasil diperbarui.');
    }

    public function updateBiodata(UpdateStudentBiodataRequest $request, Student $student): JsonResponse
    {
        return $this->update($request, $student);
    }

    /** Query dasar Data Siswa — filter dinamis via query param verification_status. */
    private function filtered(Request $request)
    {
        $verificationStatus = $request->input('verification_status', 'verified');
        if (! in_array($verificationStatus, ['verified', 'unverified', 'all'], true)) {
            $verificationStatus = 'verified';
        }

        return Student::query()
            ->with(['parent:id,name,phone', 'school:id,name'])
            ->when($verificationStatus === 'verified', fn($q) => $q->where('is_verified', true))
            ->when($verificationStatus === 'unverified', fn($q) => $q->where('is_verified', false))
            ->when($request->filled('search'), fn($q) =>
            $q->where(fn($w) => $w->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('student_code', 'like', '%' . $request->search . '%')))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('registration_type'), fn($q) => $q->where('registration_type', $request->registration_type))
            ->when($request->filled('school_id'), fn($q) => $q->where('school_id', $request->school_id))
            ->when($request->filled('program_id'), fn($q) => $q->where('program_id', $request->program_id))
            ->orderBy('name');
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(new StudentsExport($this->filtered($request)->get()), 'data-siswa.xlsx');
    }

    public function exportPdf(Request $request)
    {
        $students = $this->filtered($request)->get();
        return Pdf::loadView('exports.siswa', ['students' => $students])->download('data-siswa.pdf');
    }
}
