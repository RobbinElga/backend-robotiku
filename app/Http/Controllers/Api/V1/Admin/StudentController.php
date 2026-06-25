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
                'changed_by_type' => 'user',
                'changed_by'      => $request->user()->id,
            ]);
        });

        return $this->success($student->fresh(), 'Status siswa diperbarui.');
    }
}
