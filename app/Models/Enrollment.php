<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    protected $table = 'enrollments';
    protected $primaryKey = 'id_enrollment';
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function komisi(): BelongsTo
    {
        return $this->belongsTo(Komisi::class, 'id_komisi', 'id_komisi');
    }

    public function periodeSenat(): BelongsTo
    {
        return $this->belongsTo(PeriodeSenat::class, 'id_periode_senat', 'id_periode_senat');
    }

    public function strukturOrganisasis(): HasMany
    {
        return $this->hasMany(StrukturOrganisasi::class, 'id_enrollment', 'id_enrollment');
    }
}
