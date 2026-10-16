<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['nim_nip' => 'admin', 'nama' => 'Administrator', 'email' => 'admin@senat.test', 'role' => 'admin'],
            ['nim_nip' => '2021001', 'nama' => 'Ketua Senat', 'email' => 'ketua@senat.test', 'role' => 'anggota'],
            ['nim_nip' => '2021002', 'nama' => 'Sekretaris Senat', 'email' => 'sekretaris@senat.test', 'role' => 'anggota'],
            ['nim_nip' => '2021003', 'nama' => 'Anggota Satu', 'email' => 'anggota1@senat.test', 'role' => 'anggota'],
            ['nim_nip' => '2021004', 'nama' => 'Anggota Dua', 'email' => 'anggota2@senat.test', 'role' => 'anggota'],
        ];

        foreach ($users as $u) {
            // password di-hash otomatis oleh cast 'hashed'
            User::updateOrCreate(['email' => $u['email']], $u + ['password' => 'password']);
        }
    }
}
