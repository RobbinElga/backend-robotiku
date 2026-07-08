<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignStudentsRequest;
use App\Http\Requests\Admin\StoreClassRequest;
use App\Models\Kelas;
use App\Models\Student;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $classes = Kelas::with(['program:id,name', 'school:id,name', 'trainers:id,name'])
            ->withCount('students')
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return $this->success($classes, 'Daftar kelas.');
    }

    public function store(StoreClassRequest $request): JsonResponse
    {
        $kelas = Kelas::create($request->safe()->except('trainers'));
        $this->syncTrainers($kelas, $request->input('trainers', []));
        return $this->success($kelas->load('program:id,name', 'school:id,name', 'trainers:id,name'), 'Kelas dibuat.', 201);
    }

    public function show(Kelas $kelas): JsonResponse
    {
        $kelas->load([
            'program:id,name',
            'school:id,name',
            'trainers:id,name',
            'students:id,student_code,name,status',
        ]);
        return $this->success($kelas, 'Detail kelas.');
    }

    public function update(StoreClassRequest $request, Kelas $kelas): JsonResponse
    {
        $kelas->update($request->safe()->except('trainers'));
        $this->syncTrainers($kelas, $request->input('trainers', []));
        return $this->success($kelas->fresh()->load('program:id,name', 'school:id,name', 'trainers:id,name'), 'Kelas diperbarui.');
    }

    private function syncTrainers(Kelas $kelas, array $trainers): void
    {
        $sync = collect($trainers)->mapWithKeys(fn($t) => [$t['trainer_id'] => ['role' => $t['role']]])->all();
        $kelas->trainers()->sync($sync);
        // cache "trainer utama" ke kolom lama (dipakai isolasi absensi lama)
        $utama = collect($trainers)->firstWhere('role', 'utama')['trainer_id'] ?? (collect($trainers)->first()['trainer_id'] ?? null);
        $kelas->update(['trainer_id' => $utama]);
    }

    /** Assign murid — hanya yang program-nya sama dengan kelas. */
    public function assignStudents(AssignStudentsRequest $request, Kelas $kelas): JsonResponse
    {
        $eligible = Student::whereIn('id', $request->student_ids)
            ->verified()
            ->where('program_id', $kelas->program_id)
            ->when($kelas->school_id, fn($q) => $q->where('school_id', $kelas->school_id))
            ->when(! $kelas->school_id, fn($q) => $q->where('registration_type', 'mandiri'))
            ->pluck('id')->all();

        $existing = $kelas->students()->pluck('students.id')->all();
        $toAttach = collect($eligible)->reject(fn($id) => in_array($id, $existing))
            ->mapWithKeys(fn($id) => [$id => ['joined_at' => now()]])->all();

        if (! empty($toAttach)) $kelas->students()->attach($toAttach);

        $rejected = array_values(array_diff($request->student_ids, $eligible));
        return $this->success([
            'assigned' => array_keys($toAttach),
            'rejected' => $rejected,
            'total_in_class' => $kelas->students()->count(),
        ], $rejected ? 'Sebagian murid dilewati (beda program/sekolah).' : 'Murid ditetapkan.');
    }

    public function removeStudent(Kelas $kelas, int $studentId): JsonResponse
    {
        $kelas->students()->detach($studentId);
        return $this->success(null, 'Murid dikeluarkan dari kelas.');
    }

    public function trainers(): JsonResponse
    {
        return $this->success(
            User::where('role', 'trainer')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'Daftar trainer.'
        );
    }

    public function destroy(Kelas $kelas): JsonResponse
    {
        if ($kelas->students()->exists()) {
            return $this->error('Keluarkan semua murid dari kelas ini sebelum menghapus.', 422);
        }
        $kelas->delete();
        return $this->success(null, 'Kelas dihapus.');
    }
}
