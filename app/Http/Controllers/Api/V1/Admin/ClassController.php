<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignStudentsRequest;
use App\Http\Requests\Admin\BillingRequest;
use App\Http\Requests\Admin\StoreClassRequest;
use App\Models\BillingSetting;
use App\Models\Kelas;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;

class ClassController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $classes = Kelas::with('trainer:id,name', 'billingSetting')
            ->withCount('students')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return $this->success($classes, 'Daftar kelas.');
    }

    public function store(StoreClassRequest $request): JsonResponse
    {
        $kelas = Kelas::create($request->validated());

        return $this->success($kelas, 'Kelas dibuat.', 201);
    }

    public function show(Kelas $kelas): JsonResponse
    {
        $kelas->load([
            'trainer:id,name',
            'billingSetting',
            'students:id,student_code,name,status',
        ]);

        return $this->success($kelas, 'Detail kelas.');
    }

    public function update(StoreClassRequest $request, Kelas $kelas): JsonResponse
    {
        $kelas->update($request->validated());

        return $this->success($kelas->fresh(), 'Kelas diperbarui.');
    }

    /** Assign murid ke kelas (tanpa duplikat). */
    public function assignStudents(AssignStudentsRequest $request, Kelas $kelas): JsonResponse
    {
        $existing = $kelas->students()->pluck('students.id')->all();

        $toAttach = collect($request->student_ids)
            ->reject(fn($id) => in_array($id, $existing))
            ->mapWithKeys(fn($id) => [$id => ['joined_at' => now()]])
            ->all();

        if (! empty($toAttach)) {
            $kelas->students()->attach($toAttach);
        }

        return $this->success([
            'assigned'     => array_keys($toAttach),
            'total_in_class' => $kelas->students()->count(),
        ], 'Murid ditetapkan ke kelas.');
    }

    public function removeStudent(Kelas $kelas, int $studentId): JsonResponse
    {
        $kelas->students()->detach($studentId);

        return $this->success(null, 'Murid dikeluarkan dari kelas.');
    }

    /** Set/update harga kelas. */
    public function setBilling(BillingRequest $request, Kelas $kelas): JsonResponse
    {
        $billing = BillingSetting::updateOrCreate(
            ['class_id' => $kelas->id],
            [
                'registration_fee' => $request->registration_fee,
                'price_per_cycle'  => $request->price_per_cycle,
                'updated_by'       => $request->user()->id,
            ]
        );

        return $this->success($billing, 'Harga kelas disimpan.');
    }

    public function trainers(): JsonResponse
    {
        return $this->success(
            User::where('role', 'trainer')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'Daftar trainer.'
        );
    }
}
