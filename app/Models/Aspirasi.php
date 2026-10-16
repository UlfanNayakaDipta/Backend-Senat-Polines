<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aspirasi extends Model
{
    protected $table = 'aspirasis';
    protected $primaryKey = 'id_aspirasi';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'anonim' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriAspirasi::class, 'id_kategori', 'id_kategori');
    }

    public function tanggapans(): HasMany
    {
        return $this->hasMany(TanggapanAspirasi::class, 'id_aspirasi', 'id_aspirasi');
    }
}
