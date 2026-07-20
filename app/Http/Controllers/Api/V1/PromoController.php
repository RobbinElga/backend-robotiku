<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PromoCheckRequest;
use App\Models\Program;
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
        $program = Program::find($request->program_id);

        if (! $program) {
            return $this->error('Program tidak ditemukan.', 422);
        }

        $registrationFee = (float) $program->registration_fee;
        $pricePerCycle   = (float) $program->price_per_cycle;

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
