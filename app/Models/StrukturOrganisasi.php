<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrukturOrganisasi extends Model
{
    protected $table = 'struktur_organisasis';
    protected $primaryKey = 'id_struktur';
    protected $guarded = [];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'id_enrollment', 'id_enrollment');
    }
}
