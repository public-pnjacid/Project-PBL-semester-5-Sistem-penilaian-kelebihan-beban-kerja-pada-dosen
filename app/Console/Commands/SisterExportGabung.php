<?php

namespace App\Console\Commands;

use App\Services\SisterClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SisterExportGabung extends Command
{
    protected $signature = 'sister:export-gabung
                            {--sdm=* : id_sdm dosen (boleh berulang)}
                            {--semester= : id semester, mis. 20252}
                            {--file= : Nama file output (default: sister-gabung-TANGGAL.csv)}';

    protected $description = 'Tarik BKD ajar (semua field) + kelas & kode MK dari pengajaran SISTER, simpan ke CSV';

    // Excel dengan regional Indonesia memakai titik koma sebagai pemisah
    private const DELIMITER = ';';

    // Semua field BKD ajar (sampai stat_kegiatan) + kode_mata_kuliah & kelas dari pengajaran
    private const HEADERS = [
        'Nama Dosen', 'NIDN', 'NUPTK', 'ID Semester', 'Unsur', 'Judul Kegiatan',
        'ID Kategori Kegiatan', 'Kategori Kegiatan', 'Beban SKS', 'Nilai', 'Status Kegiatan',
        'Kode MK', 'Kelas',
    ];

    public function handle(SisterClient $sister): int
    {
        $ids = $this->option('sdm');
        if (empty($ids)) {
            $this->error('Isi opsi --sdm=ID (boleh berulang).');
            return self::FAILURE;
        }

        $semester   = $this->option('semester') ?: null;
        $result     = [];

        try {
            foreach ($ids as $id) {
                $bkd  = $sister->bkdAjar($id, $semester);
                $ajar = $sister->pengajaran($id, $semester);

                $this->line("{$id}: " . count($bkd) . ' baris BKD, ' . count($ajar) . ' baris pengajaran');

                $result = array_merge($result, $this->join($bkd, $ajar));
            }
        } catch (RuntimeException | ConnectionException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        // Peringatan di terminal saja (tidak masuk CSV)
        $counts = array_count_values(array_column($result, 'status'));
        $perluCek = ($counts['cocok (urutan)'] ?? 0) + ($counts['ambigu'] ?? 0);
        if ($perluCek > 0) {
            $this->warn("{$perluCek} baris dipasangkan berdasarkan urutan/ambigu, cek kolom Kelas secara manual.");
        }

        // Baris yang hanya ada di pengajaran tidak ikut ke CSV
        $rows = array_values(array_filter($result, fn ($r) => $r['status'] !== 'hanya di pengajaran'));

        if (empty($rows)) {
            $this->warn('Tidak ada data, CSV tidak dibuat.');
            return self::SUCCESS;
        }

        $path = $this->writeCsv($rows);
        $this->info(count($rows) . " baris disimpan ke: {$path}");

        return self::SUCCESS;
    }

    /**
     * BKD dan pengajaran tidak punya id bersama, jadi dicocokkan lewat
     * id_katgiat + nama (judul_keg di BKD = mata_kuliah di pengajaran).
     * Kalau satu mata kuliah diajar di beberapa kelas, dipasangkan berdasarkan urutan
     * dan diberi status "cocok (urutan)" agar bisa dicek manual.
     */
    private function join(array $bkd, array $ajar): array
    {
        $pool = [];
        foreach ($ajar as $a) {
            $pool[$this->key($a['id_katgiat'] ?? '', $a['mata_kuliah'] ?? '')][] = $a;
        }
        $poolCount = array_map('count', $pool);

        $bkdCount = [];
        foreach ($bkd as $b) {
            $k = $this->key($b['id_katgiat'] ?? '', $b['judul_keg'] ?? '');
            $bkdCount[$k] = ($bkdCount[$k] ?? 0) + 1;
        }

        $result = [];
        foreach ($bkd as $b) {
            $k = $this->key($b['id_katgiat'] ?? '', $b['judul_keg'] ?? '');

            if (isset($pool[$k])) {
                $a      = array_shift($pool[$k]);
                $status = match (true) {
                    $bkdCount[$k] !== $poolCount[$k] => 'ambigu',
                    $bkdCount[$k] === 1              => 'cocok',
                    default                          => 'cocok (urutan)',
                };
            } else {
                $a      = null;
                $status = 'tanpa pasangan'; // mis. bimbingan skripsi / PA, bukan mata kuliah
            }

            $result[] = ['bkd' => $b, 'ajar' => $a, 'status' => $status];
        }

        // Pengajaran yang tidak punya pasangan di BKD
        foreach ($pool as $sisa) {
            foreach ($sisa as $a) {
                $result[] = ['bkd' => null, 'ajar' => $a, 'status' => 'hanya di pengajaran'];
            }
        }

        usort($result, fn ($x, $y) => [$this->sortName($x), $x['ajar']['kelas'] ?? '']
                                  <=> [$this->sortName($y), $y['ajar']['kelas'] ?? '']);

        return $result;
    }

    private function key(mixed $idKatgiat, mixed $nama): string
    {
        return $idKatgiat . '|' . Str::lower(trim((string) $nama));
    }

    private function sortName(array $r): string
    {
        return Str::lower($r['ajar']['mata_kuliah'] ?? $r['bkd']['judul_keg'] ?? '');
    }

    private function toLine(array $r): array
    {
        $b   = $r['bkd'] ?? [];
        $a   = $r['ajar'] ?? [];
        $num = fn ($v) => ($v === null || $v === '') ? null : (float) $v;

        $line = [
            $b['nm_sdm'] ?? null,
            $b['nidn'] ?? null,
            $b['nuptk'] ?? null,
            $b['id_smt'] ?? null,
            $b['unsur'] ?? null,
            $b['judul_keg'] ?? null,
            $b['id_katgiat'] ?? null,
            $b['nm_kat'] ?? null,
            $num($b['beban_sks'] ?? null),
            $num($b['nilai'] ?? null),
            $b['stat_kegiatan'] ?? null,
            $a['kode_mata_kuliah'] ?? null,
            $a['kelas'] ?? null,
        ];

        return $line;
    }

    private function writeCsv(array $rows): string
    {
        $filename = $this->option('file') ?: 'sister-gabung-' . now()->format('Ymd-His') . '.csv';
        Storage::disk('local')->makeDirectory('sister');
        $fullPath = Storage::disk('local')->path('sister/' . $filename);

        $handle = fopen($fullPath, 'w');
        fwrite($handle, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8 dengan benar
        fputcsv($handle, self::HEADERS, self::DELIMITER, '"', '');

        foreach ($rows as $r) {
            fputcsv($handle, $this->toLine($r), self::DELIMITER, '"', '');
        }

        fclose($handle);

        return $fullPath;
    }
}