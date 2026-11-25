<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notulensi extends Model
{
    protected $table = 'notulensis';
    protected $primaryKey = 'id_notulensi';
    protected $guarded = [];

    protected $appends = ['berkas_pdf_url'];

    public function rapat(): BelongsTo
    {
        return $this->belongsTo(Rapat::class, 'id_rapat', 'id_rapat');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /** URL publik berkas PDF notulen (null bila belum dicetak). */
    protected function berkasPdfUrl(): Attribute
    {
        return Attribute::get(fn () => $this->berkas_pdf ? url($this->berkas_pdf) : null);
    }

    /** Status notulensi selalu disimpan huruf kecil: ditetapkan / dipertimbangkan / dikembalikan. */
    protected function status(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : strtolower(trim($value)));
    }
}
