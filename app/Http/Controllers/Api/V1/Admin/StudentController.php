<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StudentStatusRequest;
use App\Models\Student;
use App\Models\StudentStatusLog;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\StudentsExport;

class StudentController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $students = Student::query()
            ->with(['parent:id,name,phone', 'school:id,name'])
            ->when($request->filled('search'), fn($q) =>
            $q->where(fn($w) => $w->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('student_code', 'like', '%' . $request->search . '%')))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('registration_type'), fn($q) => $q->where('registration_type', $request->registration_type))
            ->when($request->filled('school_id'), fn($q) => $q->where('school_id', $request->school_id))
            ->when($request->boolean('unassigned'), fn($q) => $q->whereDoesntHave('classes'))
            ->when($request->filled('program_id'), fn($q) => $q->where('program_id', $request->program_id))
            ->when($request->boolean('unassigned'), fn($q) => $q->whereDoesntHave('classes'))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->success($students, 'Data siswa.');
    }

    public function show(Student $student): JsonResponse
    {
        $student->load([
            'parent:id,name,phone',
            'school:id,name',
            'classes:id,name',
            'statusLogs' => fn($q) => $q->orderByDesc('created_at'),
        ]);

        return $this->success($student, 'Detail siswa.');
    }

    public function changeStatus(StudentStatusRequest $request, Student $student): JsonResponse
    {
        $old = $student->status;
        $new = $request->status;

        if ($old === $new) {
            return $this->error('Status tidak berubah.', 422);
        }

        DB::transaction(function () use ($student, $old, $new, $request) {
            $student->update(['status' => $new]);

            StudentStatusLog::create([
                'student_id'      => $student->id,
                'old_status'      => $old,
                'new_status'      => $new,
                'note'            => $request->note,
                'changed_by_type' => 'user',
                'changed_by'      => $request->user()->id,
            ]);
        });

        return $this->success($student->fresh(), 'Status siswa diperbarui.');
    }

    private function filtered(Request $request)
    {
        return Student::query()
            ->with(['parent:id,name,phone', 'school:id,name'])
            ->when($request->filled('search'), fn($q) =>
            $q->where(fn($w) => $w->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('student_code', 'like', '%' . $request->search . '%')))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('registration_type'), fn($q) => $q->where('registration_type', $request->registration_type))
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
