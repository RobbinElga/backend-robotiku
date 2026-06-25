<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PromoCheckRequest;
use App\Models\BillingSetting;
use App\Services\PromoService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;

class PromoController extends Controller
{
    use ApiResponse;

    public function __construct(private PromoService $promo) {}

    public function check(PromoCheckRequest $request): JsonResponse
    {
        $billing = BillingSetting::where('class_id', $request->class_id)->first();

        if (! $billing) {
            return $this->error('Harga untuk kelas ini belum diatur.', 422);
        }

        $registrationFee = (float) $billing->registration_fee;
        $pricePerCycle   = (float) $billing->price_per_cycle;

        try {
            [$promo, $discount] = $this->promo->validate($request->code, $registrationFee);
        } catch (DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $total = max(0, $registrationFee - $discount) + $pricePerCycle;

        return $this->success([
            'code'             => $promo->code,
            'type'             => $promo->type,
            'value'            => $promo->value,
            'registration_fee' => $registrationFee,
            'price_per_cycle'  => $pricePerCycle,
            'discount_amount'  => $discount,
            'total_preview'    => $total,
        ], 'Kode promo valid.');
    }
}
