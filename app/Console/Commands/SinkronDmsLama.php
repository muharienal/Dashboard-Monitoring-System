<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Sinkron data DMS lama ke DMS v2 langsung dari file export (.sql), tanpa impor ke server.
 *
 *   php artisan dms:sinkron                       → pakai file db_dashboard*.sql terbaru di folder Downloads
 *   php artisan dms:sinkron "D:\folder\file.sql"  → pakai file tertentu
 *
 * Aman diulang: data yang sama diperbarui (tidak dobel), data baru ditambahkan,
 * akun yang sudah ada di DMS v2 tidak diubah.
 */
class SinkronDmsLama extends Command
{
    protected $signature = 'dms:sinkron {file? : Lokasi file export database DMS lama (.sql). Kosongkan untuk mencari otomatis di folder Downloads}';

    protected $description = 'Sinkron data DMS lama ke DMS v2 langsung dari file export .sql (tanpa impor ke server)';

    public function handle(): int
    {
        $file = $this->argument('file') ?: $this->cariDiDownloads();
        if (! $file) {
            $this->error('File db_dashboard*.sql tidak ditemukan di folder Downloads.');
            $this->line('Sebutkan lokasinya, mis.:  php artisan dms:sinkron "C:\\Users\\NAMA\\Downloads\\db_dashboard.sql"');

            return self::FAILURE;
        }
        if (! is_file($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $this->info('File   : '.realpath($file));
        $this->line('Ukuran : '.number_format(filesize($file) / 1048576, 1, ',', '.').' MB · diubah '.date('d-m-Y H:i', filemtime($file)));
        if (! $this->confirm('Sinkron data dari file ini ke DMS v2?', true)) {
            return self::SUCCESS;
        }

        return $this->call('dms:import-legacy', ['--file' => $file, '--with-master-data' => true]);
    }

    /** File db_dashboard*.sql paling baru di folder Downloads pengguna (Windows / Linux / macOS). */
    private function cariDiDownloads(): ?string
    {
        $rumah = getenv('USERPROFILE') ?: getenv('HOME') ?: '';
        $folder = $rumah.DIRECTORY_SEPARATOR.'Downloads';
        $file = glob($folder.DIRECTORY_SEPARATOR.'db_dashboard*.sql') ?: [];
        usort($file, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return $file[0] ?? null;
    }
}
