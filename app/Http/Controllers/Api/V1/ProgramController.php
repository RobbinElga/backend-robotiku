<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProgramController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $programs = Program::where('is_visible', true)
            ->orderBy('name')
            ->get(['id', 'name', 'registration_fee', 'price_per_cycle']);

        return $this->success($programs, 'Daftar program.');
    }
}
