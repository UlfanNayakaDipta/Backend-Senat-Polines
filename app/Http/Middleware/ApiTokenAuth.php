<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token authentication for the Senate REST API.
 * Supports both custom ApiToken (SHA-256) and Laravel Sanctum tokens.
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

        if ($record && ! $record->isExpired() && $record->user) {
            // Touch at most once per minute to avoid a write on every request.
            if (! $record->last_used_at || $record->last_used_at->diffInMinutes(now()) >= 1) {
                $record->forceFill(['last_used_at' => now()])->saveQuietly();
            }

            Auth::setUser($record->user);
            $request->setUserResolver(fn () => $record->user);
            $request->attributes->set('api_token', $record);

            return $next($request);
        }

        if (class_exists(PersonalAccessToken::class)) {
            $sanctumToken = PersonalAccessToken::findToken($token);
            if ($sanctumToken && $sanctumToken->tokenable) {
                Auth::setUser($sanctumToken->tokenable);
                $request->setUserResolver(fn () => $sanctumToken->tokenable);

                return $next($request);
            }
        }

        return $this->unauthorized('Token tidak valid atau sudah kedaluwarsa.');
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], 401);
    }
}
