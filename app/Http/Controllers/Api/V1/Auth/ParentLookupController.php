<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ParentLookupRequest;
use App\Models\Student;
use App\Support\Phone;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ParentLookupController extends Controller
{
    use ApiResponse;

    public function lookup(ParentLookupRequest $request): JsonResponse
    {
        // Boleh akses portal ortu:
        //  - jalur MANDIRI, ATAU
        //  - jalur INSTANSI dari sekolah yang kelola pendaftaran & pembayaran sendiri (self_managed).
        // Ortu instansi biasa tetap TIDAK boleh akses portal ini.
        $students = Student::query()
            ->where(function ($q) {
                $q->where('registration_type', 'mandiri')
                    ->orWhere(function ($x) {
                        $x->where('registration_type', 'instansi')
                            ->whereHas('school', fn($s) => $s->where('self_managed', true));
                    });
            })
            ->whereNotNull('parent_id')
            ->with(['parent:id,name,phone', 'school:id,name,self_managed'])
            ->withExists(['invoices as verified' => fn($q) => $q->where('status', 'lunas')])
            ->when($request->filled('phone'), function ($q) use ($request) {
                $phone = Phone::normalize($request->phone);
                $q->whereHas('parent', fn($p) => $p->where('phone', $phone));
            })
            ->when($request->filled('name'), function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->name . '%');
            })
            ->get(['id', 'student_code', 'name', 'parent_id', 'school_id', 'registration_type']);

        if ($students->isEmpty()) {
            return $this->error('Data tidak ditemukan. Periksa kembali nama anak atau nomor HP.', 404);
        }

        $rows = $students->map(fn($s) => [
            'id'           => $s->id,
            'student_code' => $s->student_code,
            'name'         => $s->name,
            'verified'     => (bool) $s->verified,
            'self_managed' => (bool) optional($s->school)->self_managed,
            'school'       => optional($s->school)->name,
            'parent'       => [
                'name'  => optional($s->parent)->name,
                'phone' => optional($s->parent)->phone,
            ],
        ])->values();

        return $this->success([
            // >1 anak (mis. nomor HP sama) → frontend tampilkan pemilihan anak
            'multiple' => $rows->count() > 1,
            'students' => $rows,
        ], 'Data ditemukan.');
    }
}
