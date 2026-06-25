<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Pakai: ->middleware('role:super_admin,admin')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Hanya akun internal (User) yang punya role; SchoolAdmin/Parent ditolak
        if (! $user instanceof User) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Akses ditolak: bukan akun internal.',
            ], 403);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Akses ditolak: role tidak diizinkan.',
            ], 403);
        }

        return $next($request);
    }
}
