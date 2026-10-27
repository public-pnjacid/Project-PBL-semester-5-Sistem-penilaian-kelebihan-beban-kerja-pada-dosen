<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DosenPartime extends Model
{
    protected $table = 'dosen_partime';
    protected $primaryKey = 'nip';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['nip', 'tarif_kontrak', 'tanggal_expired'];
    protected $casts = ['tanggal_expired' => 'date'];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'nip', 'nip');
    }
}