<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Handle user login authentication and issue Laravel Sanctum personal access token.
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
            // Fallback pintar: jika diinput teks polos di phpMyAdmin, cek teks langsung
            if ((string) $user->password === $password) {
                // Otomatis konversi dan simpan ulang sebagai Bcrypt hash di database!
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

        // Generate Personal Access Token via Laravel Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status'     => 'success',
            'message'    => 'Autentikasi berhasil.',
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => [
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
     * Get user profile info by Sanctum token or ID fallback.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user && $request->query('id_user')) {
            $user = User::find($request->query('id_user'));
        }

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'User tidak ditemukan atau belum terautentikasi.'], 401);
        }

        return response()->json([
            'status' => 'success',
            'user'   => $user,
        ]);
    }

    /**
     * Handle user logout (revoke active Sanctum token).
     */
    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Logout berhasil.',
        ]);
    }
}
