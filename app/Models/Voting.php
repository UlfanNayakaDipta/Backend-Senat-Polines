<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voting extends Model
{
    protected $table = 'votings';
    protected $primaryKey = 'id_voting';
    protected $guarded = [];
    protected $hidden = ['suaraVotings', 'suara_votings'];
    protected $appends = ['total_suara', 'hasil_rekap', 'kuorum'];

    /**
     * Blok kuorum kanonik dari satu sumber kebenaran Rapat::hitungKuorum(),
     * tersedia di semua payload voting (daftar, detail, buka/tutup).
     */
    public function getKuorumAttribute(): ?array
    {
        return $this->rapat?->hitungKuorum();
    }

    public function getStatusAttribute(?string $value): ?string
    {
        return match (strtolower((string) $value)) {
            'aktif', 'active', 'open', 'berlangsung' => 'dibuka',
            'selesai', 'closed' => 'ditutup',
            default => $value,
        };
    }

    protected function casts(): array
    {
        return [
            'waktu_mulai' => 'datetime',
            'waktu_selesai' => 'datetime',
        ];
    }

    public static function writeLocked(callable $callback): mixed
    {
        return \Illuminate\Support\Facades\Cache::store('file')->lock('evoting-write', 30)
            ->block(10, fn () => \Illuminate\Support\Facades\DB::transaction($callback));
    }

    public function assertEditable(): void
    {
        abort_unless($this->status === 'draft' && ! $this->suaraVotings()->exists(),
            422, 'Voting tidak dapat diubah.');
    }

    public function isUsable(): bool
    {
        $now = now();

        return $this->status === 'dibuka'
            && ($this->waktu_mulai === null || $now->greaterThanOrEqualTo($this->waktu_mulai))
            && ($this->waktu_selesai === null || $now->lessThan($this->waktu_selesai));
    }

    public function eligibility(User $user): array
    {
        $invitations = AnggotaRapat::where('id_rapat', $this->id_rapat)
            ->where('id_user', $user->id_user)->get();
        $invited = $invitations->isNotEmpty();
        $present = $invitations->contains(fn ($invitation) => $invitation->status_kehadiran === 'hadir');

        return [
            'eligible' => in_array($user->role, ['anggota', 'ketua_senat', 'sekretaris'], true)
                && $invited && $present && $this->isUsable(),
            'is_invited' => $invited,
            'is_present' => $present,
        ];
    }

    public function rapat(): BelongsTo
    {
        return $this->belongsTo(Rapat::class, 'id_rapat', 'id_rapat');
    }

    public function opsiVotings(): HasMany
    {
        return $this->hasMany(OpsiVoting::class, 'id_voting', 'id_voting');
    }

    public function suaraVotings(): HasMany
    {
        return $this->hasMany(SuaraVoting::class, 'id_voting', 'id_voting');
    }

    public function getTotalSuaraAttribute(): int
    {
        if (array_key_exists('suara_votings_count', $this->attributes)) {
            return (int) $this->attributes['suara_votings_count'];
        }

        return $this->relationLoaded('suaraVotings')
            ? $this->suaraVotings->count()
            : $this->suaraVotings()->count();
    }

    public function getHasilRekapAttribute(): array
    {
        $total = $this->total_suara;
        $opsi = $this->relationLoaded('opsiVotings')
            ? $this->opsiVotings
            : $this->opsiVotings()->withCount('suaraVotings')->get();

        return $opsi->map(function ($item) use ($total) {
            $jumlah = (int) $item->jumlah_suara;

            return [
                'id_opsi' => $item->id_opsi,
                'nama_opsi' => $item->nama_opsi,
                'jumlah_suara' => $jumlah,
                'persentase' => $total > 0 ? round(($jumlah / $total) * 100, 2) : 0,
            ];
        })->values()->all();
    }
}
