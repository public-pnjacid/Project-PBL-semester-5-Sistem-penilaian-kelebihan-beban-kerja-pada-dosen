<?php

return [
    'base_url' => env('SISTER_BASE_URL', 'https://sister-api.kemdiktisaintek.go.id/ws-sandbox.php/1.0'),

    // Field yang diminta endpoint /authorize
    'username'    => env('SISTER_USERNAME'),
    'password'    => env('SISTER_PASSWORD'),
    'id_pengguna' => env('SISTER_ID_PENGGUNA'),

    // Path endpoint data kegiatan/BKD, mis. /bkd/ajar (id_sdm & id_smt dikirim sebagai query).
    // Jika memakai placeholder {id_sdm} di path, id masuk ke path.
    'activity_path' => env('SISTER_ACTIVITY_PATH'),

    // Path endpoint "List data pengajaran pada suatu semester" (kode MK, kelas, dll).
    // Salin dari anchor URL di halaman dokumentasi (bagian setelah #get-).
    'teaching_path' => env('SISTER_PENGAJARAN_PATH'),

    // Isi false di .env hanya untuk sandbox/development
    'verify_ssl' => env('SISTER_VERIFY_SSL', true),

    'timeout' => 30,
];