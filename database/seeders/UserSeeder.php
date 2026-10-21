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
            ['nim_nip' => '2021001', 'nama' => 'Dr. Sukamto., S.Kom., M.T', 'email' => 'ketua@senat.test', 'role' => 'anggota'],
            ['nim_nip' => '2021002', 'nama' => 'Andi Setyawan., S.E., M.M', 'email' => 'sekretaris@senat.test', 'role' => 'anggota'],
            ['nim_nip' => '2021003', 'nama' => 'Anis Roihatin, S.T., M.T.', 'email' => 'anggota1@senat.test', 'role' => 'anggota'],
            ['nim_nip' => '2021004', 'nama' => 'Fajar Surya Herlambang, S.T., M.T.', 'email' => 'anggota2@senat.test', 'role' => 'anggota'],
        ];

        foreach ($users as $u) {
            // password di-hash otomatis oleh cast 'hashed'
            User::updateOrCreate(['email' => $u['email']], $u + ['password' => 'password']);
        }
    }
}
