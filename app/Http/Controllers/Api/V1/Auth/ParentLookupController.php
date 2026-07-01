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
        // Hanya jalur MANDIRI (ortu via instansi tidak boleh akses portal ini)
        $students = Student::query()
            ->where('registration_type', 'mandiri')
            ->whereNotNull('parent_id')
            ->with('parent:id,name,phone')
            ->withExists(['invoices as verified' => fn($q) => $q->where('status', 'lunas')])
            ->when($request->filled('phone'), function ($q) use ($request) {
                $phone = Phone::normalize($request->phone);
                $q->whereHas('parent', fn($p) => $p->where('phone', $phone));
            })
            ->when($request->filled('name'), function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->name . '%');
            })
            ->get(['id', 'student_code', 'name', 'parent_id']);

        if ($students->isEmpty()) {
            return $this->error('Data tidak ditemukan. Periksa kembali nama anak atau nomor HP.', 404);
        }

        return $this->success([
            // >1 anak (mis. nomor HP sama) → frontend tampilkan pemilihan anak
            'multiple' => $students->count() > 1,
            'students' => $students,
        ], 'Data ditemukan.');
    }
}
