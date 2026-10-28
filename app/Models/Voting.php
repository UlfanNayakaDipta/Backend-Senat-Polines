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
    protected $appends = ['total_suara', 'hasil_rekap'];

    protected function casts(): array
    {
        return [
            'waktu_mulai' => 'datetime',
            'waktu_selesai' => 'datetime',
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
