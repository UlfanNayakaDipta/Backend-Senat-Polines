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
}
