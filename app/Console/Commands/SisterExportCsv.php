<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SisterExportCsv extends Command
{
    protected $signature = 'sister:export-csv
                            {--sdm=* : id_sdm dosen (dipakai jika activity_path memuat {id_sdm})}
                            {--semester= : Filter id_smt, mis. 20252}
                            {--unsur=* : Filter unsur, mis. ajar}
                            {--file= : Nama file output (default: sister-kegiatan-TANGGAL.csv)}';

    protected $description = 'Tarik data kegiatan dosen dari SISTER dan simpan ke file CSV';

    private const COLUMNS = [
        'nm_sdm', 'nidn', 'nuptk', 'id_smt', 'unsur', 'judul_keg',
        'id_katgiat', 'nm_kat', 'beban_sks', 'nilai', 'stat_kegiatan',
    ];

    // Judul kolom di CSV, urutannya harus sama dengan COLUMNS
    private const HEADERS = [
        'Nama Dosen', 'NIDN', 'NUPTK', 'Semester', 'Unsur', 'Judul Kegiatan',
        'ID Kategori', 'Kategori Kegiatan', 'Beban SKS', 'Nilai', 'Status',
    ];

    // Excel dengan regional Indonesia memakai titik koma sebagai pemisah
    private const DELIMITER = ';';

    public function handle(): int
    {
        try {
            $token = $this->getToken();
            $rows  = $this->fetchActivities($token);
        } catch (RuntimeException | ConnectionException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $rows = $this->applyFilters($rows);

        if (empty($rows)) {
            $this->warn('Tidak ada data yang cocok, CSV tidak dibuat.');
            return self::SUCCESS;
        }

        $path = $this->writeCsv($rows);
        $this->info(count($rows) . " baris disimpan ke: {$path}");

        return self::SUCCESS;
    }

    private function http()
    {
        return Http::timeout(config('sister.timeout'))
            ->connectTimeout(30)
            ->when(! config('sister.verify_ssl'), fn ($req) => $req->withoutVerifying())
            ->acceptJson();
    }

    private function getToken(): string
    {
        $response = $this->http()
            ->asJson()
            ->post(config('sister.base_url') . '/authorize', [
                'username'    => config('sister.username'),
                'password'    => config('sister.password'),
                'id_pengguna' => config('sister.id_pengguna'),
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Gagal /authorize (HTTP {$response->status()}): " . Str::limit($response->body(), 500)
            );
        }

        // Sesuaikan key jika respons /authorize di Postman-mu berbeda.
        $token = $response->json('token') ?? $response->json('data.token');

        if (! $token) {
            throw new RuntimeException('Token tidak ditemukan di respons /authorize.');
        }

        return $token;
    }

    private function fetchActivities(string $token): array
    {
        $template = config('sister.activity_path');

        if (! $template) {
            throw new RuntimeException('SISTER_ACTIVITY_PATH belum diisi di .env.');
        }

        $ids = $this->option('sdm');
        if (empty($ids)) {
            throw new RuntimeException('Isi opsi --sdm=ID (boleh berulang).');
        }

        // Jika path memuat {id_sdm}, id masuk ke path; jika tidak, id dikirim sebagai query ?id_sdm=
        $usesPlaceholder = str_contains($template, '{id_sdm}');

        $all = [];
        foreach ($ids as $id) {
            $path  = str_replace('{id_sdm}', $id, $template);
            $query = array_filter([
                'id_sdm' => $usesPlaceholder ? null : $id,
                'id_smt' => $this->option('semester'),
            ]);
            $url = config('sister.base_url') . $path;

            $response = $this->http()
                ->withToken($token)
                ->get($url, $query);

            if ($response->failed()) {
                $fullUrl = $url . ($query ? '?' . http_build_query($query) : '');
                throw new RuntimeException(
                    "Gagal mengambil {$fullUrl} (HTTP {$response->status()}): " . Str::limit($response->body(), 500)
                );
            }

            $data = $response->json();
            if (! is_array($data)) {
                throw new RuntimeException("Respons {$path} bukan JSON array.");
            }

            $all = array_merge($all, $data);
        }

        return $all;
    }

    private function applyFilters(array $rows): array
    {
        $semester = $this->option('semester');
        $unsur    = $this->option('unsur');

        return array_values(array_filter($rows, function ($row) use ($semester, $unsur) {
            if ($semester && ($row['id_smt'] ?? null) !== $semester) {
                return false;
            }
            if ($unsur && ! in_array($row['unsur'] ?? null, $unsur, true)) {
                return false;
            }
            return true;
        }));
    }

    private function writeCsv(array $rows): string
    {
        $filename = $this->option('file') ?: 'sister-kegiatan-' . now()->format('Ymd-His') . '.csv';
        Storage::disk('local')->makeDirectory('sister');
        $fullPath = Storage::disk('local')->path('sister/' . $filename);

        usort($rows, fn ($a, $b) => [$a['nm_sdm'] ?? '', $a['unsur'] ?? '', $a['judul_keg'] ?? '']
                                <=> [$b['nm_sdm'] ?? '', $b['unsur'] ?? '', $b['judul_keg'] ?? '']);

        $handle = fopen($fullPath, 'w');
        fwrite($handle, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8 dengan benar
        fputcsv($handle, self::HEADERS, self::DELIMITER, '"', '');

        foreach ($rows as $row) {
            $line = [];
            foreach (self::COLUMNS as $col) {
                $value = $row[$col] ?? null;
                // beban_sks & nilai datang sebagai string, ubah jadi angka
                if (in_array($col, ['beban_sks', 'nilai'], true) && $value !== null) {
                    $value = (float) $value;
                }
                $line[] = $value;
            }
            fputcsv($handle, $line, self::DELIMITER, '"', '');
        }

        fclose($handle);

        return $fullPath;
    }
}