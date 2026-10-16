<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpsiVoting extends Model
{
    protected $table = 'opsi_votings';
    protected $primaryKey = 'id_opsi';
    protected $guarded = [];

    public function voting(): BelongsTo
    {
        return $this->belongsTo(Voting::class, 'id_voting', 'id_voting');
    }

    public function suaraVotings(): HasMany
    {
        return $this->hasMany(SuaraVoting::class, 'id_opsi', 'id_opsi');
    }
}
