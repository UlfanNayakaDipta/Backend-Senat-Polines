<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Komisi extends Model
{
    protected $table = 'komisis';
    protected $primaryKey = 'id_komisi';
    protected $guarded = [];

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'id_komisi', 'id_komisi');
    }
}
