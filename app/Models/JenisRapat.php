<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisRapat extends Model
{
    protected $table = 'jenis_rapats';
    protected $primaryKey = 'id_jenis_rapat';
    protected $guarded = [];

    protected static function booted(): void
    {
        static::deleting(function (self $jenis) {
            abort_if(
                $jenis->rapats()->whereHas('votings.suaraVotings')->exists(),
                422,
                'Jenis rapat memiliki rapat dengan riwayat suara voting dan tidak dapat dihapus.'
            );
        });
    }

    public function rapats(): HasMany
    {
        return $this->hasMany(Rapat::class, 'id_jenis_rapat', 'id_jenis_rapat');
    }
}
