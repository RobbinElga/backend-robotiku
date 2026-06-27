<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProgramController extends Controller
{
    use ApiResponse;

    /** Kelas yang sudah punya harga → bisa dipilih saat daftar. */
    public function index(): JsonResponse
    {
        $programs = Kelas::query()
            ->join('billing_settings as b', 'b.class_id', '=', 'classes.id')
            ->orderBy('classes.name')
            ->get([
                'classes.id',
                'classes.name',
                'classes.schedule',
                'b.registration_fee',
                'b.price_per_cycle',
            ]);

        return $this->success($programs, 'Daftar program tersedia.');
    }
}
