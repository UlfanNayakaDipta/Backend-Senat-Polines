<?php

namespace App\Http\Controllers;

use App\Models\AnggotaRapat;
use App\Models\Rapat;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PresensiRapatController extends Controller
{
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
                'token_qr' => $rapat->token_qr,
                'kode_sesi' => $rapat->kode_sesi,
                'is_presensi_open' => (bool) $rapat->is_presensi_open,
                'qr_expires_at' => $rapat->qr_expires_at?->toIso8601String(),
                'is_kuorum_locked' => (bool) $rapat->is_kuorum_locked,
                'kuorum' => $rapat->hitungKuorum(),
            ],
        ]);
    }

    public function generateQr(Request $request, int $id): JsonResponse
    {
        $rapat = Rapat::findOrFail($id);

        $kodeSesi = 'PLN-' . rand(100, 999);
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

    public function submitPresensi(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_user' => 'required|exists:users,id_user',
            'token_qr' => 'nullable|string',
            'kode_sesi' => 'nullable|string',
            'id_rapat' => 'nullable|integer',
        ]);

        if (empty($validated['token_qr']) && empty($validated['kode_sesi'])) {
            return response()->json([
                'success' => false,
                'message' => 'Mohon sertakan token QR hasil pemindaian atau kode sesi 6-digit.',
            ], 422);
        }

        $rapat = null;
        if (!empty($validated['token_qr'])) {
            $rapat = Rapat::where('token_qr', trim($validated['token_qr']))->first();
        }
        if (!$rapat && !empty($validated['kode_sesi'])) {
            $rapat = Rapat::where('kode_sesi', strtoupper(trim($validated['kode_sesi'])))->first();
        }
        if (!$rapat && !empty($validated['id_rapat'])) {
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

        $user = User::findOrFail($validated['id_user']);

        $undangan = AnggotaRapat::where('id_rapat', $rapat->id_rapat)
            ->where('id_user', $user->id_user)
            ->first();

        if (!$undangan) {
            $undangan = AnggotaRapat::create([
                'id_rapat' => $rapat->id_rapat,
                'id_user' => $user->id_user,
                'status_kehadiran' => 'menunggu',
            ]);
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
                'status' => $rapat->status,
                'is_presensi_open' => (bool) $rapat->is_presensi_open,
                'is_kuorum_locked' => (bool) $rapat->is_kuorum_locked,
                'token_qr' => $rapat->token_qr,
                'kode_sesi' => $rapat->kode_sesi,
                'kuorum' => $rapat->hitungKuorum(),
                'anggota' => $daftarAnggota,
            ],
        ]);
    }

    public function validasiManual(Request $request, int $id, int $id_user): JsonResponse
    {
        $validated = $request->validate([
            'status_kehadiran' => 'required|in:hadir,izin,tidak_hadir,menunggu',
            'catatan' => 'nullable|string|max:255',
        ]);

        $rapat = Rapat::findOrFail($id);

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
            'catatan_admin' => $validated['catatan'] ?: ('Divalidasi manual oleh Admin (' . ucfirst($statusSebelumnya ?: 'belum hadir') . ' -> ' . ucfirst($statusBaru) . ')'),
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
