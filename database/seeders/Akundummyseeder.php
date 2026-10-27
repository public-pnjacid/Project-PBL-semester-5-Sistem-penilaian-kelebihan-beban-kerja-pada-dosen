<?php

namespace Database\Seeders;

use App\Models\Pegawai;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AkunDummySeeder extends Seeder
{
    public function run(): void
    {
        // Data_Pegawai (dummy)
        $pegawai = Pegawai::updateOrCreate(
            ['nip' => '198001012005011001'],
            [
                'nama_pegawai' => 'Admin Dummy',
                'email'        => 'admin.dummy@example.com',
            ]
        );

        // Akun (password di-hash otomatis oleh cast 'hashed' di model User)
        User::updateOrCreate(
            ['nip' => $pegawai->nip],
            [
                'password'    => 'Dummy#12345',
                'status_akun' => 'aktif',
            ]
        );

        // Semua role kecuali Dosen
        $idRole = Role::whereIn('nama_role', ['Super Admin', 'Pimpinan', 'Keuangan'])
            ->pluck('id_role');

        $pegawai->roles()->sync($idRole);
    }
}