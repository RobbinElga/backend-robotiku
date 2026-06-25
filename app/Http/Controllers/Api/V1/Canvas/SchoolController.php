<?php

namespace App\Http\Controllers\Api\V1\Canvas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Canvas\StoreSchoolRequest;
use App\Http\Requests\Canvas\UpdateSchoolRequest;
use App\Models\School;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\Canvas\SchoolStatusRequest;
use App\Http\Requests\Canvas\StoreNoteRequest;
use App\Models\SchoolStatusLog;
use Illuminate\Support\Facades\DB;

class SchoolController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = School::query()
            ->when($request->filled('search'), fn($q) =>
            $q->where('name', 'like', '%' . $request->search . '%'))
            ->when($request->filled('status'), fn($q) =>
            $q->where('pipeline_status', $request->status))
            ->when($request->filled('updated_from'), fn($q) =>
            $q->whereDate('updated_at', '>=', $request->updated_from))
            ->when($request->filled('updated_to'), fn($q) =>
            $q->whereDate('updated_at', '<=', $request->updated_to))
            ->orderByDesc('updated_at');

        $schools = $query->paginate($request->integer('per_page', 15));

        // KPI per status (tanpa terpengaruh filter)
        $counts = School::selectRaw('pipeline_status, COUNT(*) as total')
            ->groupBy('pipeline_status')
            ->pluck('total', 'pipeline_status');

        $kpi = [
            'total'        => (int) $counts->sum(),
            'prospek'      => (int) ($counts['prospek'] ?? 0),
            'dalam_proses' => (int) ($counts['dalam_proses'] ?? 0),
            'sudah_mou'    => (int) ($counts['sudah_mou'] ?? 0),
            'tidak_lanjut' => (int) ($counts['tidak_lanjut'] ?? 0),
        ];

        return $this->success(['kpi' => $kpi, 'schools' => $schools], 'Daftar sekolah.');
    }

    public function store(StoreSchoolRequest $request): JsonResponse
    {
        $status = $request->input('pipeline_status', 'prospek');

        $school = School::create([
            ...$request->validated(),
            'pipeline_status' => $status,
            'is_mou'          => $status === 'sudah_mou',
            'created_by'      => $request->user()->id,
        ]);

        return $this->success($school, 'Sekolah ditambahkan.', 201);
    }

    public function show(School $school): JsonResponse
    {
        $school->load([
            'notes.creator:id,name',
            'statusLogs' => fn($q) => $q->orderByDesc('created_at'),
        ]);

        return $this->success($school, 'Detail sekolah.');
    }

    public function update(UpdateSchoolRequest $request, School $school): JsonResponse
    {
        $school->update($request->validated());

        return $this->success($school->fresh(), 'Data sekolah diperbarui.');
    }

    public function changeStatus(SchoolStatusRequest $request, School $school): JsonResponse
    {
        $old = $school->pipeline_status;
        $new = $request->pipeline_status;

        DB::transaction(function () use ($school, $old, $new, $request) {
            $school->update([
                'pipeline_status' => $new,
                'is_mou'          => $new === 'sudah_mou',
            ]);

            // log immutable (INSERT only)
            SchoolStatusLog::create([
                'school_id'  => $school->id,
                'old_status' => $old,
                'new_status' => $new,
                'note'       => $request->input('note'),
                'changed_by' => $request->user()->id,
            ]);
        });

        return $this->success($school->fresh(), 'Status pipeline diperbarui.');
    }

    public function addNote(StoreNoteRequest $request, School $school): JsonResponse
    {
        $note = $school->notes()->create([
            'note'       => $request->note,
            'created_by' => $request->user()->id,
        ]);

        return $this->success($note, 'Catatan ditambahkan.', 201);
    }

    public function mou(): JsonResponse
    {
        $schools = School::where('is_mou', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return $this->success($schools, 'Daftar sekolah MOU.');
    }
}
