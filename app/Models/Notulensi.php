<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notulensi extends Model
{
    protected $table = 'notulensis';
    protected $primaryKey = 'id_notulensi';
    protected $guarded = [];

    public function rapat(): BelongsTo
    {
        return $this->belongsTo(Rapat::class, 'id_rapat', 'id_rapat');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
