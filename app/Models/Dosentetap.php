<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DosenTetap extends Model
{
    protected $table = 'dosen_tetap';
    protected $primaryKey = 'nip';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['nip', 'golongan', 'jabatan_fungsional', 'program_studi'];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'nip', 'nip');
    }
}