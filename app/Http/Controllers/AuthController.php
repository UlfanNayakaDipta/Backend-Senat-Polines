<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    /**
     * Handle user login authentication and issue Laravel Sanctum personal access token.
     */
    #[OA\Post(
        path: '/login',
        summary: 'Autentikasi Login Pengguna',
        description: 'Menerima NIP atau Email dan kata sandi, memvalidasi kredensial, dan mengembalikan Bearer token Sanctum beserta data profil pengguna.',
        tags: ['Autentikasi & Akun'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['password'],
                properties: [
                    new OA\Property(property: 'identifier', type: 'string', example: '197101172003121001', description: 'NIP atau alamat email terdaftar'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123', description: 'Kata sandi pengguna')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Autentikasi berhasil, token diterbitkan'),
            new OA\Response(response: 401, description: 'Kredensial NIP/Email atau kata sandi tidak valid'),
            new OA\Response(response: 422, description: 'NIP/Email atau kata sandi tidak boleh kosong')
        ]
    )]
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

        // Generate Personal Access Token via Laravel Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        // Also issue custom ApiToken for Satria's middleware compatibility
        $expiresAt = now()->addHours((int) env('API_TOKEN_TTL_HOURS', 12));
        try {
            ApiToken::create([
                'id_user' => $user->id_user,
                'token_hash' => hash('sha256', $token),
                'name' => substr((string) $request->userAgent(), 0, 255),
                'expires_at' => $expiresAt,
            ]);

            ApiToken::where('id_user', $user->id_user)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->delete();
        } catch (\Throwable) {}

        return response()->json([
            'status'     => 'success',
            'message'    => 'Autentikasi berhasil.',
            'token'      => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
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
    #[OA\Get(
        path: '/me',
        summary: 'Informasi Profil Pengguna Aktif',
        description: 'Mengambil profil lengkap pengguna yang sedang terautentikasi melalui Bearer token.',
        tags: ['Autentikasi & Akun'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Data profil pengguna yang sedang login'),
            new OA\Response(response: 401, description: 'Pengguna belum terautentikasi atau token tidak valid')
        ]
    )]
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
     * Handle user logout (revoke active Sanctum token and ApiToken).
     */
    #[OA\Post(
        path: '/logout',
        summary: 'Logout Pengguna',
        description: 'Mencabut token sesi aktif Laravel Sanctum dan ApiToken milik pengguna saat ini.',
        tags: ['Autentikasi & Akun'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logout berhasil, token akses dicabut'),
            new OA\Response(response: 401, description: 'Belum terautentikasi')
        ]
    )]
    public function logout(Request $request)
    {
        if ($request->user() && method_exists($request->user(), 'currentAccessToken') && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        $token = $request->attributes->get('api_token');
        if ($token) {
            $token->delete();
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Logout berhasil.',
        ]);
    }

    /**
     * Impersonate a user (Admin only).
     */
    #[OA\Post(
        path: '/admin/impersonate/{id}',
        summary: 'Impersonasi Akun Anggota Senat (Khusus Admin)',
        description: 'Memungkinkan administrator untuk berganti peran dan mengakses sistem atas nama anggota senat tertentu.',
        tags: ['Autentikasi & Akun'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID Pengguna target yang akan diimpersonasi', schema: new OA\Schema(type: 'integer', example: 6))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Impersonasi berhasil dimulai, token akses target diterbitkan'),
            new OA\Response(response: 403, description: 'Akses ditolak, hanya administrator yang dapat melakukan impersonasi'),
            new OA\Response(response: 404, description: 'Pengguna target tidak ditemukan'),
            new OA\Response(response: 422, description: 'Tidak dapat melakukan impersonasi ke akun sendiri')
        ]
    )]
    public function impersonate(Request $request, $id)
    {
        $admin = $request->user();

        if (!$admin || $admin->role !== 'admin') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Hanya administrator yang dapat melakukan impersonasi user.',
            ], 403);
        }

        $targetUser = User::find($id);

        if (!$targetUser) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User target tidak ditemukan.',
            ], 404);
        }

        if ((int) $targetUser->id_user === (int) $admin->id_user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tidak dapat melakukan impersonasi ke akun sendiri.',
            ], 422);
        }

        // Generate personal access token for the target user
        $token = $targetUser->createToken('impersonated_by_' . $admin->id_user)->plainTextToken;

        return response()->json([
            'status'     => 'success',
            'message'    => 'Impersonasi berhasil dimulai.',
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => [
                'id_user'     => $targetUser->id_user,
                'nim_nip'     => $targetUser->nim_nip,
                'nama'        => $targetUser->nama,
                'email'       => $targetUser->email,
                'role'        => $targetUser->role,
                'foto_profil' => $targetUser->foto_profil,
            ],
            'impersonator' => [
                'id_user' => $admin->id_user,
                'nama'    => $admin->nama,
                'email'   => $admin->email,
                'role'    => $admin->role,
            ],
        ]);
    }
}
