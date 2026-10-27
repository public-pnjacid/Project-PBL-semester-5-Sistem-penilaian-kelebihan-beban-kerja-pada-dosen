<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Role (id_role, Nama_role)
        Schema::create('roles', function (Blueprint $t) {
            $t->id('id_role');
            $t->string('nama_role')->unique(); // Super Admin, Pimpinan, Keuangan, Dosen
            $t->timestamps();
        });

        // Data_Pegawai (NIP, Email, nama_pegawai)
        Schema::create('pegawai', function (Blueprint $t) {
            $t->string('nip')->primary();
            $t->string('nama_pegawai');
            $t->string('email')->unique();
            $t->timestamps();
        });

        // Relasi "memiliki": Role (N) <-> Data_Pegawai (N), satu pegawai 1-2 role
        Schema::create('pegawai_role', function (Blueprint $t) {
            $t->string('nip');
            $t->unsignedBigInteger('id_role');
            $t->primary(['nip', 'id_role']);
            $t->foreign('nip')->references('nip')->on('pegawai')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $t->foreign('id_role')->references('id_role')->on('roles')
                ->cascadeOnUpdate()->restrictOnDelete();
        });

        // Akun (id_akun, Username_SSO, Status_akun + password) -- relasi "Terhubung" 1:1 ke Data_Pegawai
        Schema::create('users', function (Blueprint $t) {
            $t->id('id_akun');
            $t->string('nip')->unique(); // unique = 1:1
            $t->string('username_sso')->nullable()->unique(); // diisi nanti saat SSO dipakai
            $t->string('password');
            $t->string('status_akun')->default('aktif');
            $t->rememberToken();
            $t->timestamps();
            $t->foreign('nip')->references('nip')->on('pegawai')
                ->cascadeOnUpdate()->cascadeOnDelete();
        });

        // Spesialisasi disjoint (d): Data_Pegawai -> Dosen_tetap
        Schema::create('dosen_tetap', function (Blueprint $t) {
            $t->string('nip')->primary();
            $t->string('golongan')->nullable();
            $t->string('jabatan_fungsional')->nullable();
            $t->string('program_studi')->nullable();
            $t->timestamps();
            $t->foreign('nip')->references('nip')->on('pegawai')
                ->cascadeOnUpdate()->cascadeOnDelete();
        });

        // Spesialisasi disjoint (d): Data_Pegawai -> dosen_Partime
        Schema::create('dosen_partime', function (Blueprint $t) {
            $t->string('nip')->primary();
            $t->decimal('tarif_kontrak', 15, 2)->nullable();
            $t->date('tanggal_expired')->nullable();
            $t->timestamps();
            $t->foreign('nip')->references('nip')->on('pegawai')
                ->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dosen_partime');
        Schema::dropIfExists('dosen_tetap');
        Schema::dropIfExists('users');
        Schema::dropIfExists('pegawai_role');
        Schema::dropIfExists('pegawai');
        Schema::dropIfExists('roles');
    }
};