<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisRapat extends Model
{
    protected $table = 'jenis_rapats';
    protected $primaryKey = 'id_jenis_rapat';
    protected $guarded = [];

    public function rapats(): HasMany
    {
        return $this->hasMany(Rapat::class, 'id_jenis_rapat', 'id_jenis_rapat');
    }
}
