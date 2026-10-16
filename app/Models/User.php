<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'id_user';
    protected $fillable = ['nim_nip', 'nama', 'email', 'password', 'role', 'foto_profil'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    public function aspirasis(): HasMany
    {
        return $this->hasMany(Aspirasi::class, 'id_user', 'id_user');
    }

    public function tanggapanAspirasis(): HasMany
    {
        return $this->hasMany(TanggapanAspirasi::class, 'id_user', 'id_user');
    }

    public function kegiatans(): HasMany
    {
        return $this->hasMany(Kegiatan::class, 'id_user', 'id_user');
    }

    public function rapats(): HasMany
    {
        return $this->hasMany(Rapat::class, 'id_user', 'id_user');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'id_user', 'id_user');
    }

    public function beritas(): HasMany
    {
        return $this->hasMany(Berita::class, 'id_user', 'id_user');
    }

    public function suaraVotings(): HasMany
    {
        return $this->hasMany(SuaraVoting::class, 'id_user', 'id_user');
    }

    public function anggotaRapats(): HasMany
    {
        return $this->hasMany(AnggotaRapat::class, 'id_user', 'id_user');
    }

    public function anggotaKegiatans(): HasMany
    {
        return $this->hasMany(AnggotaKegiatan::class, 'id_user', 'id_user');
    }

    public function notulensis(): HasMany
    {
        return $this->hasMany(Notulensi::class, 'id_user', 'id_user');
    }
}
