<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success($data = null, string $message = 'OK', int $code = 200): JsonResponse
    {
        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => $message,
        ], $code);
    }

    protected function error(string $message = 'Terjadi kesalahan', int $code = 400, $data = null): JsonResponse
    {
        return response()->json([
            'status'  => false,
            'data'    => $data,
            'message' => $message,
        ], $code);
    }
}
