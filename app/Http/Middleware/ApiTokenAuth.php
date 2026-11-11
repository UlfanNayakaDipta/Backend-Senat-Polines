<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token authentication for the Senate REST API.
 *
 * Tokens are opaque random strings; only the SHA-256 hash is persisted,
 * so a database leak does not expose usable credentials.
 */
class ApiTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return $this->unauthorized('Token autentikasi tidak ditemukan.');
        }

        $record = ApiToken::with('user')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $record || $record->isExpired() || ! $record->user) {
            return $this->unauthorized('Token tidak valid atau sudah kedaluwarsa.');
        }

        // Touch at most once per minute to avoid a write on every request.
        if (! $record->last_used_at || $record->last_used_at->diffInMinutes(now()) >= 1) {
            $record->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        Auth::setUser($record->user);
        $request->attributes->set('api_token', $record);

        return $next($request);
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], 401);
    }
}
