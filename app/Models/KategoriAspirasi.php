<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriAspirasi extends Model
{
    protected $table = 'kategori_aspirasis';
    protected $primaryKey = 'id_kategori';
    protected $guarded = [];

    public function aspirasis(): HasMany
    {
        return $this->hasMany(Aspirasi::class, 'id_kategori', 'id_kategori');
    }
}
