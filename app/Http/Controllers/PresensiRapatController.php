<?php

namespace App\Http\Controllers;

use App\Models\AnggotaRapat;
use App\Models\Rapat;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class PresensiRapatController extends Controller
{
    #[OA\Get(
        path: '/rapat/active',
        summary: 'Ambil agenda sidang atau rapat senat yang aktif',
        description: 'Mengambil data agenda rapat yang sedang berlangsung atau dijadwalkan hari ini beserta status kuorum. Field token_qr dan kode_sesi HANYA dikembalikan kepada pengguna terautentikasi berperan admin/ketua_senat/sekretaris; untuk pengguna lain kedua key dihilangkan sepenuhnya (bukan null).',
        tags: ['Presensi Rapat'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Data rapat aktif berhasil dimuat; token_qr/kode_sesi hanya untuk admin/ketua_senat/sekretaris'
            ),
            new OA\Response(
                response: 404,
                description: 'Tidak ada agenda sidang/rapat yang aktif'
            )
        ]
    )]
    /**
     * Token QR dan kode sesi hanya untuk sekretariat/pimpinan yang berwenang.
     */
    private function presensiSecrets(Rapat $rapat): array
    {
        $user = request()->user() ?? auth('sanctum')->user();

        if (! $user || ! in_array((string) $user->role, ['admin', 'ketua_senat', 'sekretaris'], true)) {
            return [];
        }

        return ['token_qr' => $rapat->token_qr, 'kode_sesi' => $rapat->kode_sesi];
    }

    public function getActive(): JsonResponse
    {
        $now = now();

        $rapat = Rapat::with(['jenisRapat', 'user', 'anggotaRapats.user'])
            ->where(function ($q) {
                $q->where('status', 'Sedang Berlangsung')
                  ->orWhere('is_presensi_open', true);
            })
            ->latest('tanggal')
            ->first();

        if (!$rapat) {
            $rapat = Rapat::with(['jenisRapat', 'user', 'anggotaRapats.user'])
                ->whereDate('tanggal', $now->toDateString())
                ->first();
        }

        if (!$rapat) {
            $rapat = Rapat::with(['jenisRapat', 'user', 'anggotaRapats.user'])
                ->latest('tanggal')
                ->first();
        }

        if (!$rapat) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada agenda sidang/rapat yang aktif.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data rapat aktif berhasil dimuat.',
            'data' => [
                'id_rapat' => $rapat->id_rapat,
                'nama_agenda' => $rapat->nama_agenda,
                'jenis_rapat' => $rapat->jenisRapat?->nama_jenis ?? 'Sidang Senat',
                'tanggal' => $rapat->tanggal ? $rapat->tanggal->format('Y-m-d') : null,
                'waktu' => $rapat->waktu,
                'tempat' => $rapat->tempat,
                'status' => $rapat->status,
                ...$this->presensiSecrets($rapat),
                'is_presensi_open' => (bool) $rapat->is_presensi_open,
                'qr_expires_at' => $rapat->qr_expires_at?->toIso8601String(),
                'is_kuorum_locked' => (bool) $rapat->is_kuorum_locked,
                'kuorum' => $rapat->hitungKuorum(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/rapat/{id}/generate-qr',
        summary: 'Generate QR Code & Kode Sesi Presensi Rapat',
        description: 'Membuka gerbang presensi rapat dan menghasilkan token QR dinamis serta kode sesi presensi 6 digit. Hanya admin/ketua_senat/sekretaris.',
        tags: ['Presensi Rapat'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID Rapat',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'QR presensi dan kode sesi berhasil diaktifkan'
            ),
            new OA\Response(response: 401, description: 'Belum terautentikasi'),
            new OA\Response(response: 403, description: 'Akses ditolak (hanya admin/ketua senat/sekretaris)'),
            new OA\Response(
                response: 404,
                description: 'Data rapat tidak ditemukan'
            )
        ]
    )]
    public function generateQr(Request $request, int $id): JsonResponse
    {
        $rapat = Rapat::findOrFail($id);

        $kodeSesi = Rapat::generateKodeSesi();
        $tokenQr = 'token-polines-' . strtolower(Str::slug($rapat->nama_agenda ?: 'rapat')) . '-' . Str::random(12);

        $rapat->update([
            'token_qr' => $tokenQr,
            'kode_sesi' => $kodeSesi,
            'is_presensi_open' => true,
            'status' => 'Sedang Berlangsung',
            'qr_expires_at' => now()->addHours(3),
            'is_kuorum_locked' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'QR Presensi dan Kode Sesi berhasil diaktifkan.',
            'data' => [
                'id_rapat' => $rapat->id_rapat,
                'token_qr' => $rapat->token_qr,
                'kode_sesi' => $rapat->kode_sesi,
                'is_presensi_open' => true,
                'qr_expires_at' => $rapat->qr_expires_at->toIso8601String(),
                'kuorum' => $rapat->hitungKuorum(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/rapat/{id}/freeze-presensi',
        summary: 'Bekukan Presensi dan Kunci Status Kuorum Sidang',
        description: 'Menutup sesi presensi sehingga QR Code kedaluwarsa dan status kuorum terkunci. Setelah terkunci, validasi manual presensi ditolak. Hanya admin/ketua_senat/sekretaris.',
        tags: ['Presensi Rapat'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID Rapat',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sesi presensi telah dibekukan'
            ),
            new OA\Response(response: 401, description: 'Belum terautentikasi'),
            new OA\Response(response: 403, description: 'Akses ditolak (hanya admin/ketua senat/sekretaris)'),
            new OA\Response(
                response: 404,
                description: 'Data rapat tidak ditemukan'
            )
        ]
    )]
    public function freezePresensi(Request $request, int $id): JsonResponse
    {
        $rapat = Rapat::findOrFail($id);

        $rapat->update([
            'is_presensi_open' => false,
            'qr_expires_at' => now(),
            'is_kuorum_locked' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sesi presensi telah dibekukan. QR Code kedaluwarsa dan status kuorum telah dikunci.',
            'data' => [
                'id_rapat' => $rapat->id_rapat,
                'is_presensi_open' => false,
                'is_kuorum_locked' => true,
                'kuorum' => $rapat->hitungKuorum(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/rapat/presensi',
        summary: 'Submit Presensi Kehadiran Anggota Senat',
        description: 'Mencatat kehadiran anggota senat melalui pemindaian QR code atau input kode sesi. Identitas diambil dari token; id_user hanya dihormati untuk peran sekretariat. Undangan AnggotaRapat harus sudah ada - endpoint tidak pernah membuat undangan sendiri (self-enrollment mustahil). Bila token_qr/kode_sesi dikirim namun tidak cocok, hasilnya 404 dan TIDAK ada fallback ke id_rapat; id_rapat tanpa kredensial hanya boleh dipakai bila undangan sudah ada.',
        tags: ['Presensi Rapat'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'id_user', type: 'integer', nullable: true, description: 'Hanya berlaku untuk admin/ketua_senat/sekretaris; pengguna biasa selalu tercatat atas namanya sendiri.', example: 1),
                    new OA\Property(property: 'token_qr', type: 'string', example: 'token-polines-rapat-xyz123'),
                    new OA\Property(property: 'kode_sesi', type: 'string', example: 'PLN-789'),
                    new OA\Property(property: 'id_rapat', type: 'integer', description: 'Hanya dipakai bila token_qr dan kode_sesi sama-sama kosong.', example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Presensi berhasil dicatat'
            ),
            new OA\Response(response: 401, description: 'Belum terautentikasi'),
            new OA\Response(response: 403, description: 'Aktor tidak memiliki undangan pada rapat yang ditemukan (self-enrollment dilarang)'),
            new OA\Response(
                response: 404,
                description: 'Token QR / Kode Sesi tidak cocok (tanpa fallback id_rapat)'
            ),
            new OA\Response(
                response: 422,
                description: 'Kredensial kosong tanpa id_rapat, sesi presensi ditutup/dibekukan, atau token kedaluwarsa'
            )
        ]
    )]
    public function submitPresensi(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_user' => 'nullable|exists:users,id_user',
            'token_qr' => 'nullable|string',
            'kode_sesi' => 'nullable|string',
            'id_rapat' => 'nullable|integer',
        ]);

        // Presensi selalu dicatat atas nama pemilik token, kecuali sekretariat
        // yang boleh mengoreksi presensi anggota lain.
        $actor = $request->user();
        $privileged = in_array((string) $actor->role, ['admin', 'ketua_senat', 'sekretaris'], true);
        $targetUserId = ($privileged && ! empty($validated['id_user']))
            ? (int) $validated['id_user']
            : (int) $actor->id_user;

        $hasCredential = ! empty($validated['token_qr']) || ! empty($validated['kode_sesi']);

        if (! $hasCredential && empty($validated['id_rapat'])) {
            return response()->json([
                'success' => false,
                'message' => 'Mohon sertakan token QR hasil pemindaian atau kode sesi 6-digit.',
            ], 422);
        }

        $rapat = null;
        if ($hasCredential) {
            if (!empty($validated['token_qr'])) {
                $rapat = Rapat::where('token_qr', trim($validated['token_qr']))->first();
            }
            if (!$rapat && !empty($validated['kode_sesi'])) {
                $rapat = Rapat::where('kode_sesi', strtoupper(trim($validated['kode_sesi'])))->first();
            }
            if (! $rapat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token QR atau Kode Sesi tidak valid / tidak ditemukan pada sistem persidangan.',
                ], 404);
            }
        } elseif (! empty($validated['id_rapat'])) {
            $rapat = Rapat::find($validated['id_rapat']);
        }

        if (!$rapat) {
            return response()->json([
                'success' => false,
                'message' => 'Token QR atau Kode Sesi tidak valid / tidak ditemukan pada sistem persidangan.',
            ], 404);
        }

        if (!$rapat->is_presensi_open) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi presensi untuk rapat ini sudah ditutup / dibekukan oleh Sekretariat.',
            ], 422);
        }

        if ($rapat->qr_expires_at && now()->isAfter($rapat->qr_expires_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Masa berlaku token QR / Kode Sesi presensi telah kedaluwarsa.',
            ], 422);
        }

        $user = User::findOrFail($targetUserId);

        $undangan = AnggotaRapat::where('id_rapat', $rapat->id_rapat)
            ->where('id_user', $user->id_user)
            ->first();

        if (!$undangan) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak terdaftar sebagai peserta rapat ini. Hubungi Sekretariat untuk undangan presensi.',
            ], 403);
        }

        if (strtolower((string) $undangan->status_kehadiran) === 'hadir') {
            return response()->json([
                'success' => true,
                'already_recorded' => true,
                'message' => 'Anda telah tercatat Hadir sebelumnya pada pukul ' . ($undangan->waktu_presensi ? $undangan->waktu_presensi->format('H:i') : '-') . ' WIB.',
                'data' => [
                    'id_rapat' => $rapat->id_rapat,
                    'nama_agenda' => $rapat->nama_agenda,
                    'user_nama' => $user->nama,
                    'waktu_presensi' => $undangan->waktu_presensi?->toIso8601String(),
                    'kuorum' => $rapat->hitungKuorum(),
                ],
            ]);
        }

        $metode = !empty($validated['token_qr']) ? 'qr_scan' : 'token_input';
        $undangan->update([
            'status_kehadiran' => 'hadir',
            'waktu_presensi' => now(),
            'metode_presensi' => $metode,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Presensi berhasil dicatat! Kehadiran Anda telah terverifikasi.',
            'data' => [
                'id_rapat' => $rapat->id_rapat,
                'nama_agenda' => $rapat->nama_agenda,
                'user_nama' => $user->nama,
                'status_kehadiran' => 'hadir',
                'metode_presensi' => $metode,
                'waktu_presensi' => $undangan->waktu_presensi->format('H:i:s d/m/Y'),
                'kuorum' => $rapat->hitungKuorum(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/rapat/{id}/live-attendance',
        summary: 'Monitor Kehadiran Sidang dan Kuorum Real-time',
        description: 'Mengambil daftar anggota rapat beserta status kehadiran (hadir/izin/menunggu), waktu scan, dan persentase kuorum. Field token_qr dan kode_sesi HANYA dikembalikan kepada pengguna terautentikasi berperan admin/ketua_senat/sekretaris; untuk pengguna lain kedua key dihilangkan sepenuhnya (bukan null).',
        tags: ['Presensi Rapat'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID Rapat',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Daftar kehadiran real-time; token_qr/kode_sesi hanya untuk admin/ketua_senat/sekretaris'
            ),
            new OA\Response(
                response: 404,
                description: 'Data rapat tidak ditemukan'
            )
        ]
    )]
    public function getLiveAttendance(int $id): JsonResponse
    {
        $rapat = Rapat::with(['jenisRapat', 'anggotaRapats.user'])->findOrFail($id);

        $daftarAnggota = $rapat->anggotaRapats->map(function ($a) {
            return [
                'id_undangan' => $a->id_undangan,
                'id_user' => $a->id_user,
                'nama' => $a->user?->nama ?? 'Anggota Senat',
                'nim_nip' => $a->user?->nim_nip ?? '-',
                'role' => $a->user?->role ?? 'anggota',
                'status_kehadiran' => $a->status_kehadiran ?: 'menunggu',
                'waktu_presensi' => $a->waktu_presensi ? $a->waktu_presensi->format('H:i:s') : null,
                'metode_presensi' => $a->metode_presensi,
                'is_edited_by_admin' => (bool) $a->is_edited_by_admin,
                'catatan_admin' => $a->catatan_admin,
            ];
        })->sortByDesc(function ($item) {
            return $item['status_kehadiran'] === 'hadir' ? 2 : ($item['status_kehadiran'] === 'izin' ? 1 : 0);
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'id_rapat' => $rapat->id_rapat,
                'nama_agenda' => $rapat->nama_agenda,
                'jenis_rapat' => $rapat->jenisRapat?->nama_jenis ?? 'Sidang Senat',
                'tanggal' => $rapat->tanggal ? $rapat->tanggal->format('Y-m-d') : null,
                'waktu' => $rapat->waktu ? substr($rapat->waktu, 0, 5) . ' WIB' : '09:00 WIB',
                'tempat' => $rapat->tempat ?? 'Ruang Sidang Senat',
                'status' => $rapat->status,
                'is_presensi_open' => (bool) $rapat->is_presensi_open,
                'is_kuorum_locked' => (bool) $rapat->is_kuorum_locked,
                ...$this->presensiSecrets($rapat),
                'kuorum' => $rapat->hitungKuorum(),
                'anggota' => $daftarAnggota,
            ],
        ]);
    }

    #[OA\Put(
        path: '/rapat/{id}/anggota/{id_user}/validasi',
        summary: 'Validasi Manual Kehadiran Anggota oleh Admin/Sekretariat',
        description: 'Mengubah status presensi anggota secara manual disertai catatan resmi, HANYA oleh admin/ketua_senat/sekretaris. Ditolak 422 bila status kuorum sidang sudah terkunci (is_kuorum_locked).',
        tags: ['Presensi Rapat'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID Rapat',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'id_user',
                in: 'path',
                required: true,
                description: 'ID Pengguna / Anggota Senat',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status_kehadiran'],
                properties: [
                    new OA\Property(
                        property: 'status_kehadiran',
                        type: 'string',
                        enum: ['hadir', 'izin', 'tidak_hadir', 'menunggu'],
                        example: 'hadir'
                    ),
                    new OA\Property(
                        property: 'catatan',
                        type: 'string',
                        example: 'Hadir langsung di ruang sidang lantai 2'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Status kehadiran berhasil divalidasi manual'
            ),
            new OA\Response(response: 401, description: 'Belum terautentikasi'),
            new OA\Response(response: 403, description: 'Akses ditolak (hanya admin/ketua senat/sekretaris)'),
            new OA\Response(
                response: 422,
                description: 'Status kehadiran tidak valid atau status kuorum sidang sudah terkunci'
            ),
            new OA\Response(
                response: 404,
                description: 'Rapat atau anggota tidak ditemukan'
            )
        ]
    )]
    public function validasiManual(Request $request, int $id, int $id_user): JsonResponse
    {
        $validated = $request->validate([
            'status_kehadiran' => 'required|in:hadir,izin,tidak_hadir,menunggu',
            'catatan' => 'nullable|string|max:255',
        ]);

        $rapat = Rapat::findOrFail($id);

        if ($rapat->is_kuorum_locked) {
            return response()->json([
                'success' => false,
                'message' => 'Status kuorum sidang telah dikunci. Perubahan presensi tidak lagi diizinkan.',
            ], 422);
        }

        $undangan = AnggotaRapat::where('id_rapat', $id)
            ->where('id_user', $id_user)
            ->firstOrFail();

        $statusSebelumnya = $undangan->status_kehadiran;
        $statusBaru = $validated['status_kehadiran'];

        $undangan->update([
            'status_kehadiran' => $statusBaru,
            'waktu_presensi' => $statusBaru === 'hadir' ? ($undangan->waktu_presensi ?: now()) : null,
            'metode_presensi' => 'manual_admin',
            'is_edited_by_admin' => true,
            'catatan_admin' => ($validated['catatan'] ?? null) ?: ('Divalidasi manual oleh Admin (' . ucfirst($statusSebelumnya ?: 'belum hadir') . ' -> ' . ucfirst($statusBaru) . ')'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status kehadiran anggota berhasil diperbarui dengan cap verifikasi Admin.',
            'data' => [
                'undangan' => [
                    'id_undangan' => $undangan->id_undangan,
                    'id_user' => $undangan->id_user,
                    'status_kehadiran' => $undangan->status_kehadiran,
                    'is_edited_by_admin' => (bool) $undangan->is_edited_by_admin,
                    'catatan_admin' => $undangan->catatan_admin,
                    'waktu_presensi' => $undangan->waktu_presensi?->format('H:i:s'),
                ],
                'kuorum' => $rapat->hitungKuorum(),
            ],
        ]);
    }
}
