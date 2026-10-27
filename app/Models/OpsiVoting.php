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
    protected $appends = ['jumlah_suara'];

    public function voting(): BelongsTo
    {
        return $this->belongsTo(Voting::class, 'id_voting', 'id_voting');
    }

    public function suaraVotings(): HasMany
    {
        return $this->hasMany(SuaraVoting::class, 'id_opsi', 'id_opsi');
    }

    public function getJumlahSuaraAttribute(): int
    {
        if (array_key_exists('suara_votings_count', $this->attributes)) {
            return (int) $this->attributes['suara_votings_count'];
        }

        return $this->relationLoaded('suaraVotings')
            ? $this->suaraVotings->count()
            : $this->suaraVotings()->count();
    }
}
