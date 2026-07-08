<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PeriodController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $periods = Period::with('school:id,name')
            ->when($request->filled('scope'), fn($q) => $q->where('scope', $request->scope))
            ->when($request->filled('school_id'), fn($q) => $q->where('school_id', $request->school_id))
            ->orderBy('scope')->orderBy('number')
            ->get();
        return $this->success($periods, 'Daftar periode.');
    }

    public function store(Request $request): JsonResponse
    {
        $period = Period::create($this->rules($request));
        return $this->success($period->load('school:id,name'), 'Periode dibuat.', 201);
    }

    public function update(Request $request, Period $period): JsonResponse
    {
        $period->update($this->rules($request));
        return $this->success($period->fresh()->load('school:id,name'), 'Periode diperbarui.');
    }

    public function destroy(Period $period): JsonResponse
    {
        $period->delete();
        return $this->success(null, 'Periode dihapus.');
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'name'      => ['required', 'string', 'max:120'],
            'scope'     => ['required', Rule::in(['sekolah', 'mandiri'])],
            'school_id' => ['nullable', 'required_if:scope,sekolah', 'exists:schools,id'],
            'number'    => ['required', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);
    }
}
