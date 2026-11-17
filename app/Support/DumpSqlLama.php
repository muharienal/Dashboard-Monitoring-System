<?php

namespace App\Support;

use RuntimeException;

/**
 * Membaca file dump SQL DMS lama (hasil Export phpMyAdmin / mysqldump) LANGSUNG dari file,
 * tanpa perlu mengimpornya ke server database.
 *
 * File dibaca baris per baris (hemat memori, cocok untuk file ratusan MB). Hanya pernyataan
 * INSERT INTO untuk tabel yang diminta yang diurai; tabel lain (mis. availability_data) dilewati.
 * Mendukung INSERT dengan daftar kolom (phpMyAdmin) maupun tanpa daftar kolom (urutan dari CREATE TABLE).
 */
final class DumpSqlLama
{
    public function __construct(private readonly string $path)
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("File tidak ditemukan atau tidak bisa dibaca: {$path}");
        }
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * Ambil seluruh baris beberapa tabel kecil sekaligus (satu kali baca file).
     *
     * @param  string[]  $tabel
     * @return array<string, array<int, object>>
     */
    public function ambil(array $tabel): array
    {
        $hasil = array_fill_keys($tabel, []);
        foreach ($this->baris($tabel) as [$t, $row]) {
            $hasil[$t][] = $row;
        }

        return $hasil;
    }

    /**
     * Generator [nama_tabel, object baris] sesuai urutan di file.
     *
     * @param  string[]  $tabel
     */
    public function baris(array $tabel): \Generator
    {
        $fh = fopen($this->path, 'rb');
        if (! $fh) {
            throw new RuntimeException("File tidak bisa dibuka: {$this->path}");
        }
        $kolomCreate = [];   // urutan kolom dari CREATE TABLE, untuk INSERT tanpa daftar kolom
        $create = null;      // sedang membaca CREATE TABLE tabel ini
        $aktif = null;       // null = di luar INSERT; '' = INSERT tabel yang dilewati; nama = INSERT tabel yang diurai
        $kolom = [];
        try {
            while (($line = fgets($fh)) !== false) {
                if ($aktif === null) {
                    if ($create !== null) {
                        if (preg_match('/^\s+`([^`]+)`\s/', $line, $m)) {
                            $kolomCreate[$create][] = $m[1];
                        } elseif (str_starts_with(ltrim($line), ')')) {
                            $create = null;
                        }

                        continue;
                    }
                    if (preg_match('/^CREATE TABLE (?:IF NOT EXISTS )?`([^`]+)`/i', $line, $m)) {
                        $create = $m[1];
                        $kolomCreate[$create] = [];

                        continue;
                    }
                    if (! preg_match('/^INSERT INTO `([^`]+)`\s*(?:\(([^)]*)\))?\s*VALUES\s*/i', $line, $m)) {
                        continue;
                    }
                    $t = $m[1];
                    if (! in_array($t, $tabel, true)) {
                        $aktif = '';
                    } else {
                        $aktif = $t;
                        $kolom = ($m[2] ?? '') !== '' ? array_map(fn ($c) => trim($c, " `\t"), explode(',', $m[2])) : ($kolomCreate[$t] ?? []);
                        if (! $kolom) {
                            throw new RuntimeException("Daftar kolom tabel {$t} tidak ditemukan di file.");
                        }
                    }
                    $line = substr($line, strlen($m[0]));
                }

                if ($aktif !== '') {
                    foreach (self::tuple($line) as $nilai) {
                        if (count($nilai) !== count($kolom)) {
                            throw new RuntimeException("Jumlah nilai tidak cocok dengan kolom tabel {$aktif}.");
                        }
                        yield [$aktif, (object) array_combine($kolom, $nilai)];
                    }
                }
                if (str_ends_with(rtrim($line), ';')) {
                    $aktif = null;
                }
            }
        } finally {
            fclose($fh);
        }
    }

    /**
     * Semua tuple "( ... )" dalam satu baris teks INSERT.
     *
     * @return array<int, array<int, string|null>>
     */
    private static function tuple(string $line): array
    {
        $str = '\'(?:[^\'\\\\]|\\\\.|\'\')*+\'';
        if (! preg_match_all('/\(((?:[^()\'"]++|'.$str.'|"(?:[^"\\\\]|\\\\.)*+")*+)\)/s', $line, $mm)) {
            return [];
        }
        $hasil = [];
        foreach ($mm[1] as $isi) {
            preg_match_all('/\s*('.$str.'|"(?:[^"\\\\]|\\\\.)*+"|[^,]*?)\s*(?:,|$)/s', $isi, $vv);
            $nilai = [];
            foreach ($vv[1] as $i => $v) {
                // pola terakhir kosong karena (?:,|$) bisa cocok di akhir string
                if ($i === count($vv[1]) - 1 && $v === '' && ! str_ends_with(rtrim($isi), ',')) {
                    break;
                }
                $nilai[] = self::nilai($v);
            }
            $hasil[] = $nilai;
        }

        return $hasil;
    }

    private static function nilai(string $v): ?string
    {
        if (strcasecmp($v, 'NULL') === 0) {
            return null;
        }
        $q = $v[0] ?? '';
        if (($q === "'" || $q === '"') && strlen($v) >= 2) {
            // Satu tahap: \x (escape backslash) dan '' (kutip ganda) diurai bersamaan.
            return preg_replace_callback('/\\\\(.)|'.preg_quote($q.$q, '/').'/s', fn ($m) => ! isset($m[1]) ? $q : match ($m[1]) {
                '0' => "\0", 'n' => "\n", 'r' => "\r", 't' => "\t", 'Z' => "\x1a", 'b' => "\x08",
                default => $m[1],
            }, substr($v, 1, -1));
        }

        return $v;
    }
}
