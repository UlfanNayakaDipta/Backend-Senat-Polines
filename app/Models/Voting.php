<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voting extends Model
{
    protected $table = 'votings';
    protected $primaryKey = 'id_voting';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'waktu_mulai' => 'datetime',
            'waktu_selesai' => 'datetime',
        ];
    }

    public function rapat(): BelongsTo
    {
        return $this->belongsTo(Rapat::class, 'id_rapat', 'id_rapat');
    }

    public function opsiVotings(): HasMany
    {
        return $this->hasMany(OpsiVoting::class, 'id_voting', 'id_voting');
    }

    public function suaraVotings(): HasMany
    {
        return $this->hasMany(SuaraVoting::class, 'id_voting', 'id_voting');
    }
}
