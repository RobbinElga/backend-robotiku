<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDiscountRequest;
use App\Http\Requests\Admin\UpdateDiscountRequest;
use App\Models\DiscountCode;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscountCodeController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $codes = DiscountCode::query()
            ->when($request->filled('search'), fn($q) => $q->where('code', 'like', '%' . strtoupper($request->search) . '%'))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->success($codes, 'Daftar kode promo.');
    }

    public function store(StoreDiscountRequest $request): JsonResponse
    {
        $code = DiscountCode::create([
            ...$request->validated(),
            'used_count' => 0,
            'is_active'  => $request->boolean('is_active', true),
            'created_by' => $request->user()->id,
        ]);

        return $this->success($code, 'Kode promo dibuat.', 201);
    }

    public function show(DiscountCode $discountCode): JsonResponse
    {
        return $this->success($discountCode, 'Detail kode promo.');
    }

    public function update(UpdateDiscountRequest $request, DiscountCode $discountCode): JsonResponse
    {
        $discountCode->update($request->validated());

        return $this->success($discountCode->fresh(), 'Kode promo diperbarui.');
    }

    public function destroy(DiscountCode $discountCode): JsonResponse
    {
        if ($discountCode->used_count > 0) {
            return $this->error('Kode sudah pernah dipakai. Nonaktifkan saja, jangan hapus.', 422);
        }

        $discountCode->delete();

        return $this->success(null, 'Kode promo dihapus.');
    }
}
