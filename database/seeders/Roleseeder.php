<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Super Admin', 'Pimpinan', 'Keuangan', 'Dosen'] as $nama) {
            Role::firstOrCreate(['nama_role' => $nama]);
        }
    }
}