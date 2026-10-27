<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TarikSdmSister extends Command
{
    protected $signature = 'sister:tarik-sdm';
    protected $description = 'Menarik id_sdm dari SISTER API secara bertahap dan tahan timeout';

    public function handle()
    {
        // Masukkan token JWT aktif SISTER Live (ws.php)
        $token = 'RMpryzl/0zD12PvRp5EoILfYqqoEOBs6IKvDHZH6ao0LgQpRjMRkROA+cETqO0ez/49Wmhn6wQslusg+bF9vMSp+fCjZL4eNleiF1VRUESr88rQSEguhDbpAcpHdSlhP';
        $idSp  = '7030c216-d878-4a7c-bc7d-5f58473a50aa';
        $url   = 'https://sister-api.kemdiktisaintek.go.id/ws.php/1.0/referensi/sdm';

        $csvPath = base_path('daftar_nama_dosen_postman.csv');
        if (!file_exists($csvPath)) {
            $this->error("File CSV input tidak ditemukan di: {$csvPath}");
            return 1;
        }

        // Buka file CSV input dan deteksi pemisah kolom
        $handle = fopen($csvPath, 'r');
        $firstLine = fgets($handle);
        $delimiter = (strpos($firstLine, ';') !== false) ? ';' : ',';
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        $namaIndex = array_search('Nama', $header);

        if ($namaIndex === false) {
            $this->error("Kolom 'Nama' tidak ditemukan di header CSV!");
            fclose($handle);
            return 1;
        }

        // Siapkan file output (tulis header baru dengan id_sdm)
        $outputPath = base_path('hasil_dosen_sister.csv');
        $outHandle = fopen($outputPath, 'w');
        $headerOut = $header;
        $headerOut[] = 'id_sdm';
        fputcsv($outHandle, $headerOut, $delimiter);

        $this->info("Menjalankan penarikan data (tahan timeout)...");

        $no = 1;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (empty($row) || !isset($row[$namaIndex])) {
                continue;
            }

            $nama = trim($row[$namaIndex]);
            $idSdm = '';

            try {
                // Beri timeout 30 detik dan retry hingga 2 kali jika koneksi lambat
                $response = Http::withoutVerifying()
                    ->retry(2, 500)
                    ->withToken($token)
                    ->withHeaders([
                        'Accept' => 'application/json',
                    ])
                    ->timeout(30)
                    ->get($url, [
                        'id_sp' => $idSp,
                        'nama'  => $nama,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $records = isset($data['data']) ? $data['data'] : $data;

                    if (!empty($records) && is_array($records)) {
                        $first = isset($records[0]) ? $records[0] : $records;
                        $idSdm = $first['id_sdm'] ?? '';
                        $this->info("[{$no}] [SUKSES] {$nama} -> {$idSdm}");
                    } else {
                        $this->warn("[{$no}] [TIDAK DITEMUKAN] {$nama}");
                    }
                } else {
                    $this->error("[{$no}] [HTTP {$response->status()}] {$nama}");
                    if ($response->status() === 401) {
                        $this->error("Token kedaluwarsa!");
                        break;
                    }
                }
            } catch (\Exception $e) {
                // Tangkap timeout tanpa menghentikan perulangan seluruh baris
                $this->error("[{$no}] [TIMEOUT / GAGAL KONEKSI] {$nama}");
            }

            // Langsung simpan baris ke CSV saat itu juga
            $rowOut = $row;
            $rowOut[] = $idSdm;
            fputcsv($outHandle, $rowOut, $delimiter);

            $no++;
            usleep(300000); // Beri jeda 0.3 detik agar tidak membebani server SISTER
        }

        fclose($handle);
        fclose($outHandle);

        $this->info("\nSelesai! Data aman tersimpan di: {$outputPath}");
        return 0;
    }
}