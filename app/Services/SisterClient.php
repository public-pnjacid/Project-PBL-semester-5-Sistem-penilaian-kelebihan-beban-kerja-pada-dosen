<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SisterClient
{
    private ?string $token = null;

    private function http()
    {
        return Http::timeout(config('sister.timeout'))
            ->connectTimeout(30)
            ->when(! config('sister.verify_ssl'), fn ($req) => $req->withoutVerifying())
            ->acceptJson();
    }

    public function token(): string
    {
        if ($this->token) {
            return $this->token;
        }

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

        $token = $response->json('token') ?? $response->json('data.token');

        if (! $token) {
            throw new RuntimeException('Token tidak ditemukan di respons /authorize.');
        }

        return $this->token = $token;
    }

    /** GET ke SISTER dengan Bearer token, mengembalikan JSON array. */
    public function get(string $path, array $query = []): array
    {
        $url = config('sister.base_url') . $path;

        $response = $this->http()
            ->withToken($this->token())
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

        return $data;
    }

    /** Data BKD pengajaran (GET /bkd/ajar). */
    public function bkdAjar(string $idSdm, ?string $semester = null): array
    {
        $template = config('sister.activity_path');
        if (! $template) {
            throw new RuntimeException('SISTER_ACTIVITY_PATH belum diisi di .env.');
        }

        $usesPlaceholder = str_contains($template, '{id_sdm}');

        return $this->get(
            str_replace('{id_sdm}', $idSdm, $template),
            array_filter([
                'id_sdm' => $usesPlaceholder ? null : $idSdm,
                'id_smt' => $semester,
            ])
        );
    }

    /** Data pengajaran: kode MK, kelas, dll (List data pengajaran pada suatu semester). */
    public function pengajaran(string $idSdm, ?string $semester = null): array
    {
        $path = config('sister.teaching_path');
        if (! $path) {
            throw new RuntimeException('SISTER_PENGAJARAN_PATH belum diisi di .env.');
        }

        return $this->get($path, array_filter([
            'id_sdm'      => $idSdm,
            'id_semester' => $semester,
        ]));
    }
}