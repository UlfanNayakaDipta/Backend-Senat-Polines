<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuaraVoting extends Model
{
    protected $table = 'suara_votings';
    protected $primaryKey = 'id_suara';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'waktu_vote' => 'datetime',
        ];
    }

    public function voting(): BelongsTo
    {
        return $this->belongsTo(Voting::class, 'id_voting', 'id_voting');
    }

    public function opsiVoting(): BelongsTo
    {
        return $this->belongsTo(OpsiVoting::class, 'id_opsi', 'id_opsi');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
