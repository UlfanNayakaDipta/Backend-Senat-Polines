<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Peraturan extends Model
{
    protected $table = 'peraturans';
    protected $primaryKey = 'id_peraturan';
    protected $guarded = [];

    public function periodeSenat(): BelongsTo
    {
        return $this->belongsTo(PeriodeSenat::class, 'id_periode_senat', 'id_periode_senat');
    }
}
