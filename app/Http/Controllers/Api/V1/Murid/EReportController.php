<?php

namespace App\Http\Controllers\Api\V1\Murid;

use App\Http\Controllers\Controller;
use App\Http\Requests\Murid\EReportRequest;
use App\Models\EReport;
use App\Models\Student;
use App\Models\User;
use App\Support\ImageStorage;
use App\Support\Phone;
use App\Traits\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EReportController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $reports = EReport::with('student:id,name,student_code')
            ->when($request->filled('student_id'), fn($q) => $q->where('student_id', $request->student_id))
            ->when($request->filled('year'), fn($q) => $q->where('year', $request->year))
            ->orderByDesc('year')
            ->paginate($request->integer('per_page', 20));

        return $this->success($reports, 'Daftar E-Rapot.');
    }

    public function store(EReportRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($err = $this->guardTrainer($user, $request->student_id, $request->class_id)) {
            return $err;
        }

        $exists = EReport::where('student_id', $request->student_id)
            ->where('semester', $request->semester)->where('year', $request->year)->exists();
        if ($exists) {
            return $this->error('E-Rapot siswa untuk semester & tahun ini sudah ada. Gunakan edit.', 422);
        }

        $data = $request->safe()->except('signature');
        $data['trainer_id'] = $user->id;

        if ($request->hasFile('signature')) {
            $data['signature_image'] = ImageStorage::storeWebp($request->file('signature'), 'signatures', 'public');
        }

        $report = EReport::create($data);

        return $this->success($report, 'E-Rapot dibuat.', 201);
    }

    public function update(EReportRequest $request, EReport $eReport): JsonResponse
    {
        $user = $request->user();
        if ($err = $this->guardTrainer($user, $request->student_id, $request->class_id)) {
            return $err;
        }

        $data = $request->safe()->except('signature');
        if ($request->hasFile('signature')) {
            $data['signature_image'] = ImageStorage::storeWebp($request->file('signature'), 'signatures', 'public');
        }

        $eReport->update($data);

        return $this->success($eReport->fresh(), 'E-Rapot diperbarui.');
    }

    public function show(EReport $eReport): JsonResponse
    {
        return $this->success($eReport->load('student:id,name,student_code', 'trainer:id,name', 'kelas:id,name'), 'Detail E-Rapot.');
    }

    /** Cetak PDF (internal). */
    public function pdf(EReport $eReport): Response
    {
        return $this->renderPdf($eReport);
    }

    /** Ortu: daftar E-Rapot anak (verifikasi via HP). */
    public function parentList(Request $request): JsonResponse
    {
        $request->validate(['student_id' => ['required', 'integer'], 'phone' => ['required', 'string']]);

        $student = Student::with('parent')->find($request->student_id);
        if (! $student || optional($student->parent)->phone !== Phone::normalize($request->phone)) {
            return $this->error('Data tidak cocok. Periksa nomor HP.', 403);
        }

        $reports = EReport::where('student_id', $student->id)->orderByDesc('year')->get();

        return $this->success($reports, 'E-Rapot anak.');
    }

    /** Ortu: download PDF (verifikasi via HP). */
    public function parentPdf(Request $request, EReport $eReport): Response
    {
        $request->validate(['phone' => ['required', 'string']]);
        $eReport->load('student.parent');

        if (optional($eReport->student->parent)->phone !== Phone::normalize($request->phone)) {
            abort(403, 'Nomor HP tidak cocok.');
        }

        return $this->renderPdf($eReport);
    }

    /* ---------- helpers ---------- */

    private function guardTrainer(?object $user, int $studentId, int $classId): ?JsonResponse
    {
        if ($user instanceof User && $user->role === 'trainer') {
            $teaches = \App\Models\Kelas::where('id', $classId)->where('trainer_id', $user->id)->exists()
                && \App\Models\ClassStudent::where('class_id', $classId)->where('student_id', $studentId)->exists();
            if (! $teaches) {
                return $this->error('Anda hanya bisa menilai murid di kelas Anda.', 403);
            }
        }

        return null;
    }

    private function renderPdf(EReport $eReport): Response
    {
        $eReport->load('student:id,name,student_code,school_grade', 'trainer:id,name', 'kelas:id,name');

        $pdf = Pdf::loadView('erapot.pdf', ['r' => $eReport])->setPaper('a4', 'portrait');

        return $pdf->download("E-Rapot-{$eReport->student->student_code}-S{$eReport->semester}-{$eReport->year}.pdf");
    }
}
