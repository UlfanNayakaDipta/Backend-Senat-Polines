<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RapatRekaman extends Model
{
    protected $table = 'rapat_rekamans';
    protected $primaryKey = 'id_rekaman';
    protected $guarded = [];

    protected $casts = [
        'ukuran' => 'integer',
        'durasi' => 'integer',
        'progres' => 'integer',
    ];

    public function rapat(): BelongsTo
    {
        return $this->belongsTo(Rapat::class, 'id_rapat', 'id_rapat');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
