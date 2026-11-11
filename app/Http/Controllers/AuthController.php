<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Handle user login authentication.
     */
    public function login(Request $request)
    {
        $identifier = trim($request->input('identifier') ?? $request->input('email') ?? $request->input('nim_nip') ?? '');
        $password   = (string) $request->input('password', '');

        if (empty($identifier) || empty($password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'NIP/Email dan kata sandi wajib diisi.',
            ], 422);
        }

        // Cek akun berdasarkan email atau nim_nip
        $user = User::where('email', $identifier)
            ->orWhere('nim_nip', $identifier)
            ->first();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'NIP/Email atau kata sandi yang Anda masukkan salah.',
            ], 401);
        }

        $passwordValid = false;

        // Cek apakah format password di database merupakan Bcrypt hash
        $isBcrypt = str_starts_with($user->password, '$2y$') 
                 || str_starts_with($user->password, '$2a$') 
                 || str_starts_with($user->password, '$2b$');

        if ($isBcrypt) {
            $passwordValid = Hash::check($password, $user->password);
        } else {
            // Legacy row stored as plaintext (imported directly via phpMyAdmin).
            // Compare in constant time, then upgrade the row to a bcrypt hash.
            if (hash_equals((string) $user->password, $password)) {
                $user->password = Hash::make($password);
                $user->save();
                $passwordValid = true;
            }
        }

        if (!$passwordValid) {
            return response()->json([
                'status'  => 'error',
                'message' => 'NIP/Email atau kata sandi yang Anda masukkan salah.',
            ], 401);
        }




        // Issue an opaque bearer token; only its SHA-256 hash is stored.
        $token = Str::random(64);
        $expiresAt = now()->addHours((int) env('API_TOKEN_TTL_HOURS', 12));

        ApiToken::create([
            'id_user' => $user->id_user,
            'token_hash' => hash('sha256', $token),
            'name' => substr((string) $request->userAgent(), 0, 255),
            'expires_at' => $expiresAt,
        ]);

        // Best-effort cleanup of this user's expired tokens.
        ApiToken::where('id_user', $user->id_user)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Autentikasi berhasil.',
            'token'   => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'user'    => [
                'id_user'     => $user->id_user,
                'nim_nip'     => $user->nim_nip,
                'nama'        => $user->nama,
                'email'       => $user->email,
                'role'        => $user->role,
                'foto_profil' => $user->foto_profil,
            ],
        ]);
    }

    /**
     * Get the profile of the currently authenticated user.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Tidak terautentikasi.'], 401);
        }

        return response()->json([
            'status' => 'success',
            'user'   => $user,
        ]);
    }

    /**
     * Revoke the bearer token used for this request.
     */
    public function logout(Request $request)
    {
        $token = $request->attributes->get('api_token');

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Logout berhasil.',
        ]);
    }
}
