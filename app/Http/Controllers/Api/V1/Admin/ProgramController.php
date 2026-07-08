<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProgramRequest;
use App\Models\Program;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $programs = Program::withCount(['students', 'classes'])
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return $this->success($programs, 'Daftar program.');
    }

    public function store(ProgramRequest $request): JsonResponse
    {
        $program = Program::create($request->validated());
        return $this->success($program, 'Program dibuat.', 201);
    }

    public function show(Program $program): JsonResponse
    {
        $program->loadCount(['students', 'classes',]);
        return $this->success($program, 'Detail program.');
    }

    public function update(ProgramRequest $request, Program $program): JsonResponse
    {
        $program->update($request->validated());
        return $this->success($program->fresh(), 'Program diperbarui.');
    }

    public function destroy(Program $program): JsonResponse
    {
        if ($program->students()->exists() || $program->classes()->exists()) {
            return $this->error('Program masih dipakai siswa/kelas. Tidak bisa dihapus.', 422);
        }
        $program->delete();
        return $this->success(null, 'Program dihapus.');
    }
}
