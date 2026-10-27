<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'id_role';

    protected $fillable = ['nama_role'];

    public function pegawai()
    {
        return $this->belongsToMany(Pegawai::class, 'pegawai_role', 'id_role', 'nip');
    }
}