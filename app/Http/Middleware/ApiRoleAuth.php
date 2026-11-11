<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coarse role gate for privileged REST endpoints.
 * Runs after ApiTokenAuth, so $request->user() is already resolved.
 */
class ApiRoleAuth
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Tidak terautentikasi.'], 401);
        }

        if (! empty($roles) && ! in_array((string) $user->role, $roles, true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden. Peran Anda tidak memiliki izin untuk tindakan ini.',
            ], 403);
        }

        return $next($request);
    }
}
