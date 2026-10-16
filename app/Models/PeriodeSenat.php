<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodeSenat extends Model
{
    protected $table = 'periode_senats';
    protected $primaryKey = 'id_periode_senat';
    protected $guarded = [];

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'id_periode_senat', 'id_periode_senat');
    }

    public function peraturans(): HasMany
    {
        return $this->hasMany(Peraturan::class, 'id_periode_senat', 'id_periode_senat');
    }
}
