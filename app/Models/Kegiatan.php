<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kegiatan extends Model
{
    protected $table = 'kegiatans';
    protected $primaryKey = 'id_kegiatan';
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function rapats(): HasMany
    {
        return $this->hasMany(Rapat::class, 'id_kegiatan', 'id_kegiatan');
    }

    public function anggotaKegiatans(): HasMany
    {
        return $this->hasMany(AnggotaKegiatan::class, 'id_kegiatan', 'id_kegiatan');
    }
}
