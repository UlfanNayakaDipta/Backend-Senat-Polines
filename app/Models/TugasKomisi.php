<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TugasKomisi extends Model
{
    protected $table = 'tugas_komisis';
    protected $primaryKey = 'id_tugas';
    protected $guarded = [];

    public function komisi(): BelongsTo
    {
        return $this->belongsTo(Komisi::class, 'id_komisi', 'id_komisi');
    }
}
