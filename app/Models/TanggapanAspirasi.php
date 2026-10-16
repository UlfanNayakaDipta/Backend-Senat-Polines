<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TanggapanAspirasi extends Model
{
    protected $table = 'tanggapan_aspirasis';
    protected $primaryKey = 'id_tanggapan';
    protected $guarded = [];

    public function aspirasi(): BelongsTo
    {
        return $this->belongsTo(Aspirasi::class, 'id_aspirasi', 'id_aspirasi');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
