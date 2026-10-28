<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Rapat extends Model
{
    protected $table = 'rapats';
    protected $primaryKey = 'id_rapat';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'is_presensi_open' => 'boolean',
            'is_kuorum_locked' => 'boolean',
            'qr_expires_at' => 'datetime',
        ];
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class, 'id_kegiatan', 'id_kegiatan');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function jenisRapat(): BelongsTo
    {
        return $this->belongsTo(JenisRapat::class, 'id_jenis_rapat', 'id_jenis_rapat');
    }

    public function anggotaRapats(): HasMany
    {
        return $this->hasMany(AnggotaRapat::class, 'id_rapat', 'id_rapat');
    }

    public function materiRapats(): HasMany
    {
        return $this->hasMany(MateriRapat::class, 'id_rapat', 'id_rapat');
    }

    public function notulensi(): HasOne
    {
        return $this->hasOne(Notulensi::class, 'id_rapat', 'id_rapat');
    }

    public function votings(): HasMany
    {
        return $this->hasMany(Voting::class, 'id_rapat', 'id_rapat');
    }

    /**
     * Hitung status kuorum rapat berdasarkan peserta undangan
     * Sesuai ketentuan: Hanya status 'hadir' yang dihitung, izin tidak dihitung.
     */
    public function hitungKuorum(): array
    {
        $anggotas = $this->anggotaRapats()->get();
        $totalInvited = $anggotas->count();

        $hadirCount = $anggotas->filter(fn ($a) => strtolower((string) $a->status_kehadiran) === 'hadir')->count();
        $izinCount = $anggotas->filter(fn ($a) => strtolower((string) $a->status_kehadiran) === 'izin')->count();
        $tidakHadirCount = $anggotas->filter(fn ($a) => in_array(strtolower((string) $a->status_kehadiran), ['tidak_hadir', 'tidak hadir', 'alpa']))->count();
        $menungguCount = max(0, $totalInvited - ($hadirCount + $izinCount + $tidakHadirCount));

        $minPercent = (int) ($this->kuorum_min_persen ?: 67);
        $ambangKuorum = $totalInvited > 0 ? (int) ceil($totalInvited * ($minPercent / 100)) : 0;
        $presentPercentage = $totalInvited > 0 ? round(($hadirCount / $totalInvited) * 100, 1) : 0;
        $isKuorum = $totalInvited > 0 && ($hadirCount >= $ambangKuorum);

        return [
            'total_invited' => $totalInvited,
            'present_count' => $hadirCount,
            'izin_count' => $izinCount,
            'tidak_hadir_count' => $tidakHadirCount,
            'menunggu_count' => $menungguCount,
            'kuorum_min_persen' => $minPercent,
            'ambang_kuorum' => $ambangKuorum,
            'present_percentage' => $presentPercentage,
            'is_kuorum' => $isKuorum,
            'status_kuorum_label' => $isKuorum ? 'Kuorum Terpenuhi' : 'Belum Memenuhi Kuorum',
            'is_presensi_open' => (bool) $this->is_presensi_open,
            'is_kuorum_locked' => (bool) $this->is_kuorum_locked,
        ];
    }
}
