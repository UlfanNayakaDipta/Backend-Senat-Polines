<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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

        return $this->issueAuthResponse($request, $user, 'auth_token', 'Autentikasi berhasil.');
    }

    /**
     * Handle Google Sign-In authentication for verified POLINES domain accounts.
     */
    #[OA\Post(
        path: '/login/google',
        summary: 'Autentikasi Login via Akun Google (@polines.ac.id)',
        description: 'Memverifikasi identitas Google OAuth (bridge SSO terotentikasi atau token Google). Akun dengan domain polines.ac.id diizinkan masuk dan otomatis didaftarkan sebagai anggota jika belum terdapat di database.',
        tags: ['Autentikasi & Akun'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'dosen@polines.ac.id'),
                    new OA\Property(property: 'name', type: 'string', example: 'Dr. Dosen Polines'),
                    new OA\Property(property: 'id_token', type: 'string', description: 'Google OAuth ID token'),
                    new OA\Property(property: 'access_token', type: 'string', description: 'Google OAuth access token'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Autentikasi Google berhasil, token diterbitkan'),
            new OA\Response(response: 401, description: 'Kredensial atau tanda tangan SSO Google tidak valid'),
            new OA\Response(response: 403, description: 'Email Google bukan domain resmi polines.ac.id'),
        ]
    )]
    public function loginWithGoogle(Request $request): JsonResponse
    {
        $profile = $this->resolveVerifiedGoogleProfile($request);
        if ($profile === null) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Kredensial Google SSO tidak valid atau gagal diverifikasi.',
            ], 401);
        }

        $verifiedEmail = $profile['email'];
        if (!$this->isPolinesEmail($verifiedEmail)) {
            return response()->json([
                'status'  => 'error',
                'message' => "Akses ditolak. Akun Google ({$verifiedEmail}) wajib menggunakan domain resmi POLINES (*@polines.ac.id).",
            ], 403);
        }

        $user = User::whereRaw('LOWER(email) = ?', [$verifiedEmail])->first();
        if (!$user) {
            $fallbackName = Str::title(str_replace(['.', '_', '-'], ' ', Str::before($verifiedEmail, '@')));
            $displayName = trim((string) ($profile['name'] ?? '')) !== ''
                ? Str::limit(trim((string) $profile['name']), 255, '')
                : ($fallbackName !== '' ? $fallbackName : 'Pengguna Polines');

            $picture = isset($profile['picture']) && is_string($profile['picture']) && strlen($profile['picture']) <= 255
                ? $profile['picture']
                : null;

            $user = User::create([
                'nim_nip'     => $this->generateUniqueSsoNip(),
                'nama'        => $displayName,
                'email'       => $verifiedEmail,
                'password'    => Str::random(40),
                'role'        => 'anggota',
                'foto_profil' => $picture,
            ]);
        }

        return $this->issueAuthResponse($request, $user, 'auth_token_google', 'Autentikasi Google berhasil.');
    }

    /**
     * Validate institutional POLINES email domains (@polines.ac.id and subdomains).
     */
    private function isPolinesEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && preg_match('/@(?:[a-z0-9-]+\.)*polines\.ac\.id$/i', $email) === 1;
    }

    private function generateUniqueSsoNip(): string
    {
        do {
            $candidate = 'SSO-' . strtoupper(Str::random(10));
        } while (User::where('nim_nip', $candidate)->exists());

        return $candidate;
    }

    /**
     * Verify Google SSO caller using signed frontend bridge headers or direct Google tokens.
     *
     * @return array{email: string, name: string, picture: ?string}|null
     */
    private function resolveVerifiedGoogleProfile(Request $request): ?array
    {
        $ssoSecret = (string) config('services.google.sso_secret', '');
        $signature = (string) $request->header('X-Google-Bridge-Signature', '');
        $timestamp = (string) $request->header('X-Google-Bridge-Timestamp', '');
        $inputEmail = strtolower(trim((string) $request->input('email', '')));

        if ($ssoSecret !== '' && ($signature !== '' || $timestamp !== '')) {
            if ($inputEmail === '' || !ctype_digit($timestamp)) {
                return null;
            }
            if (abs(time() - (int) $timestamp) > 300) {
                return null;
            }
            $expected = hash_hmac('sha256', "{$inputEmail}|{$timestamp}", $ssoSecret);

            if (!hash_equals($expected, $signature)) {
                return null;
            }

            return [
                'email'   => $inputEmail,
                'name'    => trim((string) $request->input('name', '')),
                'picture' => $request->input('picture') ? (string) $request->input('picture') : null,
            ];
        }

        $idToken = trim((string) $request->input('id_token', ''));
        if ($idToken !== '') {
            try {
                $res = $this->googleHttp()->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
                if (!$res->successful()) {
                    return null;
                }
                $claims = $res->json();
                $verified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $email = strtolower(trim((string) ($claims['email'] ?? '')));
                $clientId = trim((string) config('services.google.client_id', ''));
                if ($clientId !== '' && ($claims['aud'] ?? '') !== $clientId) {
                    return null;
                }

                if (!$verified || $email === '') {
                    return null;
                }

                return [
                    'email'   => $email,
                    'name'    => trim((string) ($claims['name'] ?? '')),
                    'picture' => isset($claims['picture']) ? (string) $claims['picture'] : null,
                ];
            } catch (\Throwable) {
                return null;
            }
        }

        $accessToken = trim((string) $request->input('access_token', ''));
        if ($accessToken !== '') {
            try {
                $res = $this->googleHttp()->withToken($accessToken)->get('https://www.googleapis.com/oauth2/v3/userinfo');
                if (!$res->successful()) {
                    return null;
                }
                $profile = $res->json();
                $verified = filter_var($profile['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $email = strtolower(trim((string) ($profile['email'] ?? '')));

                if (!$verified || $email === '') {
                    return null;
                }

                return [
                    'email'   => $email,
                    'name'    => trim((string) ($profile['name'] ?? '')),
                    'picture' => isset($profile['picture']) ? (string) $profile['picture'] : null,
                ];
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Build an HTTP client for Google APIs that honors local XAMPP CA bundles without disabling TLS verification.
     */
    private function googleHttp(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(8)->withOptions([
            'force_ip_resolve' => 'v4',
            'verify' => $this->resolveGoogleCaVerify(),
        ]);
    }

    private function resolveGoogleCaVerify(): string|bool
    {
        foreach ([trim((string) ini_get('curl.cainfo')), trim((string) ini_get('openssl.cafile'))] as $configured) {
            if ($configured !== '' && is_file($configured)) {
                return $configured;
            }
        }

        $phpRoot = dirname(dirname(PHP_BINARY));
        foreach ([
            $phpRoot . '/phpMyAdmin/vendor/composer/ca-bundle/res/cacert.pem',
            $phpRoot . '/php/extras/ssl/cacert.pem',
            $phpRoot . '/apache/bin/curl-ca-bundle.crt',
            'D:/xampp/phpMyAdmin/vendor/composer/ca-bundle/res/cacert.pem',
            'D:/xampp/apache/bin/curl-ca-bundle.crt',
            'C:/xampp/apache/bin/curl-ca-bundle.crt',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return true;
    }

    /**
     * Issue Sanctum + ApiToken pair and standardized auth payload for an authenticated user.
     */
    private function issueAuthResponse(
        Request $request,
        User $user,
        string $tokenName = 'auth_token',
        string $message = 'Autentikasi berhasil.',
    ): JsonResponse {
        // Default 72 jam (3 hari) agar selaras dengan kebijakan verifikasi ulang sesi.
        $expiresAt = now()->addHours(max(1, (int) env('API_TOKEN_TTL_HOURS', 72)));

        // Generate Personal Access Token via Laravel Sanctum dengan masa berlaku eksplisit
        $token = $user->createToken($tokenName, ['*'], $expiresAt)->plainTextToken;

        // Also issue custom ApiToken for middleware compatibility
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
            'message'    => $message,
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
