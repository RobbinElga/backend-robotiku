<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SchoolAdminLoginRequest;
use App\Models\SchoolAdmin;
use App\Support\Phone;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SchoolAdminAuthController extends Controller
{
    use ApiResponse;

    public function login(SchoolAdminLoginRequest $request): JsonResponse
    {
        $login = $request->input('login');
        $phone = Phone::normalize($login);

        $admin = SchoolAdmin::where('email', $login)
            ->orWhere('phone', $phone)
            ->first();

        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            return $this->error('Email/HP atau password salah.', 401);
        }

        if (! $admin->is_active) {
            return $this->error('Akun nonaktif. Hubungi administrator.', 403);
        }

        $token = $admin->createToken('school-admin')->plainTextToken;

        return $this->success([
            'token' => $token,
            'admin' => [
                'id'        => $admin->id,
                'name'      => $admin->name,
                'school_id' => $admin->school_id,
            ],
        ], 'Login berhasil.');
    }

    public function changePassword(Request $request): JsonResponse
    {
        $admin = $request->user(); // SchoolAdmin via Sanctum

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! \Illuminate\Support\Facades\Hash::check($data['current_password'], $admin->password)) {
            return $this->error('Password saat ini salah.', 422);
        }

        $admin->update(['password' => \Illuminate\Support\Facades\Hash::make($data['password'])]);
        $admin->tokens()->where('id', '!=', $admin->currentAccessToken()->id)->delete(); // logout perangkat lain
        return $this->success(null, 'Password berhasil diperbarui.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logout berhasil.');
    }
}
