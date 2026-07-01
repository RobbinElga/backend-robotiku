<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SchoolAdminRequest;
use App\Models\SchoolAdmin;
use App\Support\Phone;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SchoolAdminController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $admins = SchoolAdmin::with('school:id,name')
            ->when($request->filled('search'), fn($q) => $q->where(function ($w) use ($request) {
                $s = '%' . $request->search . '%';
                $w->where('name', 'like', $s)->orWhere('email', 'like', $s)->orWhere('phone', 'like', $s);
            }))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return $this->success($admins, 'Daftar akun sekolah.');
    }

    public function store(SchoolAdminRequest $request): JsonResponse
    {
        $data = $request->validated();
        $admin = SchoolAdmin::create([
            'school_id' => $data['school_id'],
            'name'      => $data['name'],
            'email'     => $data['email'] ?? null,
            'phone'     => isset($data['phone']) ? Phone::normalize($data['phone']) : null,
            'password'  => Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $this->success($admin->load('school:id,name'), 'Akun sekolah dibuat.', 201);
    }

    public function update(SchoolAdminRequest $request, SchoolAdmin $schoolAdmin): JsonResponse
    {
        $data = $request->validated();
        $schoolAdmin->update([
            'school_id' => $data['school_id'],
            'name'      => $data['name'],
            'email'     => $data['email'] ?? null,
            'phone'     => isset($data['phone']) ? Phone::normalize($data['phone']) : null,
            'is_active' => $data['is_active'] ?? $schoolAdmin->is_active,
        ]);

        return $this->success($schoolAdmin->fresh()->load('school:id,name'), 'Akun sekolah diperbarui.');
    }

    public function resetPassword(Request $request, SchoolAdmin $schoolAdmin): JsonResponse
    {
        $request->validate(['password' => ['required', 'string', 'min:6']]);
        $schoolAdmin->update(['password' => Hash::make($request->password)]);

        return $this->success(null, 'Kata sandi berhasil direset.');
    }

    public function toggleActive(SchoolAdmin $schoolAdmin): JsonResponse
    {
        $schoolAdmin->update(['is_active' => ! $schoolAdmin->is_active]);
        return $this->success(['is_active' => $schoolAdmin->is_active], 'Status akun diperbarui.');
    }
}
