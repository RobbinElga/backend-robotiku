<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success(BankAccount::orderBy('bank_name')->get(), 'Daftar rekening Robotiku.');
    }

    /** Dipakai Admin Sekolah untuk lihat tujuan transfer (hanya yang aktif). */
    public function active(): JsonResponse
    {
        return $this->success(BankAccount::where('is_active', true)->orderBy('bank_name')->get(), 'Rekening aktif.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->rules($request);
        return $this->success(BankAccount::create($data), 'Rekening ditambahkan.', 201);
    }

    public function update(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $bankAccount->update($this->rules($request));
        return $this->success($bankAccount->fresh(), 'Rekening diperbarui.');
    }

    public function destroy(BankAccount $bankAccount): JsonResponse
    {
        $bankAccount->delete();
        return $this->success(null, 'Rekening dihapus.');
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'bank_name'      => ['required', 'string', 'max:60'],
            'account_number' => ['required', 'string', 'max:40'],
            'account_holder' => ['required', 'string', 'max:120'],
            'is_active'      => ['boolean'],
        ]);
    }
}
