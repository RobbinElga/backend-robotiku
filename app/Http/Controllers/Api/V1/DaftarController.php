<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DaftarMandiriRequest;
use App\Services\RegistrationService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;

class DaftarController extends Controller
{
    use ApiResponse;

    public function __construct(private RegistrationService $registration) {}

    public function mandiri(DaftarMandiriRequest $request): JsonResponse
    {
        try {
            $result = $this->registration->registerMandiri($request->validated());
        } catch (DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'student' => [
                'id'           => $result['student']->id,
                'student_code' => $result['student']->student_code,
                'name'         => $result['student']->name,
            ],
            'invoice' => [
                'invoice_number'  => $result['invoice']->invoice_number,
                'total_amount'    => $result['invoice']->total_amount,
                'discount_amount' => $result['invoice']->discount_amount,
                'due_date'        => $result['invoice']->due_date,
                'status'          => $result['invoice']->status,
            ],
        ], 'Pendaftaran berhasil. Silakan lakukan pembayaran sesuai tagihan.', 201);
    }
}
