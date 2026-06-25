<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetPasswordRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->filled('role'), fn($q) => $q->where('role', $request->role))
            ->when($request->filled('search'), fn($q) =>
            $q->where(fn($w) => $w->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('email', 'like', '%' . $request->search . '%')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return $this->success($users, 'Daftar akun.');
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            ...$request->validated(),
            'is_active' => true,
        ]); // password otomatis di-hash (cast 'hashed')

        return $this->success($user, 'Akun dibuat.', 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());

        return $this->success($user->fresh(), 'Akun diperbarui.');
    }

    public function resetPassword(ResetPasswordRequest $request, User $user): JsonResponse
    {
        $user->update(['password' => $request->password]); // di-hash via cast

        return $this->success(null, 'Password berhasil direset.');
    }

    public function toggleActive(Request $request, User $user): JsonResponse
    {
        $request->validate(['is_active' => ['required', 'boolean']]);

        // Cegah Super Admin menonaktifkan dirinya sendiri
        if ($user->id === $request->user()->id && ! $request->boolean('is_active')) {
            return $this->error('Anda tidak bisa menonaktifkan akun sendiri.', 422);
        }

        $user->update(['is_active' => $request->boolean('is_active')]);

        return $this->success($user->fresh(), 'Status akun diperbarui.');
    }
}
