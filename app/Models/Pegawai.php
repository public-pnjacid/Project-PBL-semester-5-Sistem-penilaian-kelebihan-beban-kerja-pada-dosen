<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pegawai extends Model
{
    protected $table = 'pegawai';
    protected $primaryKey = 'nip';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['nip', 'nama_pegawai', 'email'];

    public function akun()
    {
        return $this->hasOne(User::class, 'nip', 'nip');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'pegawai_role', 'nip', 'id_role');
    }

    public function dosenTetap()
    {
        return $this->hasOne(DosenTetap::class, 'nip', 'nip');
    }

    public function dosenPartime()
    {
        return $this->hasOne(DosenPartime::class, 'nip', 'nip');
    }
}