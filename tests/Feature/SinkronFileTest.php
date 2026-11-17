<?php

namespace Tests\Feature;

use App\Support\DumpSqlLama;
use Illuminate\Support\Facades\DB;

/** Sinkron data DMS lama langsung dari file export .sql (tanpa impor ke server). */
class SinkronFileTest extends DmsTestCase
{
    private function tulis(string $isi): string
    {
        $f = tempnam(sys_get_temp_dir(), 'dump').'.sql';
        file_put_contents($f, $isi);

        return $f;
    }

    private function dumpLama(): string
    {
        return $this->tulis(<<<'SQL'
-- phpMyAdmin SQL Dump
CREATE TABLE `bidang` (
  `id_bidang` int(11) NOT NULL,
  `nama_bidang` varchar(50) NOT NULL
) ENGINE=InnoDB;
INSERT INTO `bidang` (`id_bidang`, `nama_bidang`) VALUES
(1, 'ANGKUTAN LUAR'),
(2, 'ALBER WIL 2 FI');
CREATE TABLE `jenis_kendaraan` (
  `id_jenis` int(11) NOT NULL,
  `id_bidang` int(11) NOT NULL,
  `nama_jenis` varchar(50) NOT NULL
) ENGINE=InnoDB;
INSERT INTO `jenis_kendaraan` (`id_jenis`, `id_bidang`, `nama_jenis`) VALUES
(1, 1, 'DUMP TRUK B3'),
(2, 2, 'FORKLIFT');
CREATE TABLE `kendaraan` (
  `id_kendaraan` int(11) NOT NULL,
  `id_jenis` int(11) NOT NULL,
  `nomor_kendaraan` varchar(50) NOT NULL
) ENGINE=InnoDB;
INSERT INTO `kendaraan` VALUES (1,1,'W 1 AA'),(2,2,'FL (01), O''Neil');
CREATE TABLE `availability_data` (
  `id` int(11) NOT NULL
) ENGINE=InnoDB;
INSERT INTO `availability_data` (`id`) VALUES
(1),
(2);
CREATE TABLE `sheet_data` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `bidang` varchar(50),
  `jenis_kendaraan` varchar(50),
  `nomor_kendaraan` varchar(50),
  `jalan` int(11), `standby` int(11), `rusak` int(11), `perbaikan` int(11), `pm` int(11),
  `utilitas_jalan` int(11), `utilitas_standby` int(11), `utilitas_rusak` int(11),
  `updated_at` timestamp NULL
) ENGINE=InnoDB;
INSERT INTO `sheet_data` (`id`, `tanggal`, `bidang`, `jenis_kendaraan`, `nomor_kendaraan`, `jalan`, `standby`, `rusak`, `perbaikan`, `pm`, `utilitas_jalan`, `utilitas_standby`, `utilitas_rusak`, `updated_at`) VALUES
(1, '2026-10-01', 'ANGKUTAN LUAR', 'DUMP TRUK B3', 'W 1 AA', 10, 14, 0, 0, 0, 1, 0, 0, '2026-10-01 03:00:00'),
(2, '2026-10-01', 'ALBER WIL 2 FI', 'FORKLIFT', 'FL (01), O\'Neil', 30, 0, -2, 0, 0, 1, 0, 0, NULL),
(3, '2026-10-02', 'ANGKUTAN LUAR', 'WING BOX', 'L 9 ZZ', 0, 4, 0, 20, 0, 0, 0, 1, '2026-10-02 01:00:00');
CREATE TABLE `users` (
  `id` int(11) NOT NULL, `username` varchar(50), `email` varchar(100), `password` varchar(255), `role` varchar(20), `created_at` timestamp NULL
) ENGINE=InnoDB;
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'Budi \"B\"', 'Budi@gmail.com', '$2y$10$abcdefghijklmnopqrstuuJ0Fz2QWm1Z9W1q3rC0vXkX1sZtKpK6y', 'bidang', NULL);
SQL);
    }

    public function test_pembaca_dump_menangani_kutip_kurung_null_dan_tanpa_daftar_kolom(): void
    {
        $d = new DumpSqlLama($this->dumpLama());
        $t = $d->ambil(['kendaraan', 'users', 'bidang']);
        $this->assertSame(['W 1 AA', "FL (01), O'Neil"], array_column($t['kendaraan'], 'nomor_kendaraan'));
        $this->assertSame('Budi "B"', $t['users'][0]->username);
        $this->assertNull($t['users'][0]->created_at);
        $sheet = iterator_to_array((function () use ($d) { foreach ($d->baris(['sheet_data']) as [, $r]) { yield $r; } })(), false);
        $this->assertCount(3, $sheet);
        $this->assertSame("FL (01), O'Neil", $sheet[1]->nomor_kendaraan);
        $this->assertSame('-2', $sheet[1]->rusak);
        $this->assertNull($sheet[1]->updated_at);
    }

    public function test_sinkron_dari_file_sama_dengan_aturan_impor(): void
    {
        $this->assertSame(0, $this->artisan('dms:sinkron', ['file' => $this->dumpLama(), '--no-interaction' => true]));
        $baris = DB::table('sheet_data as s')->join('kendaraan as k', 'k.id', '=', 's.kendaraan_id')->orderBy('s.tanggal')->orderBy('k.nomor_kendaraan')
            ->get(['k.nomor_kendaraan', 's.jalan', 's.standby', 's.rusak', 's.perbaikan', 's.utilitas_jalan', 's.utilitas_rusak', 's.updated_at']);
        $this->assertCount(3, $baris);
        // Jam tidak wajar dirapikan: jalan 30 -> 24, rusak -2 -> 0, standby dihitung ulang
        $fl = $baris->firstWhere('nomor_kendaraan', "FL (01), O'Neil");
        $this->assertSame([24, 0, 0], [(int) $fl->jalan, (int) $fl->standby, (int) $fl->rusak]);
        // Waktu UTC -> WIB
        $this->assertSame('2026-10-01 10:00:00', $baris->firstWhere('nomor_kendaraan', 'W 1 AA')->updated_at);
        // Kendaraan yang hanya ada di riwayat: non-aktif; alias bidang ALBER WIL 2 FI -> ALBER WIL 3
        $this->assertDatabaseHas('kendaraan', ['nomor_kendaraan' => 'L 9 ZZ', 'is_active' => 0, 'keterangan' => 'Dari data lama']);
        $this->assertSame('ALBER WIL 3', DB::table('kendaraan as k')->join('jenis_kendaraan as j', 'j.id', '=', 'k.jenis_kendaraan_id')
            ->join('bidang as b', 'b.id', '=', 'j.bidang_id')->where('k.nomor_kendaraan', "FL (01), O'Neil")->value('b.nama'));
        // Akun baru dengan password lama; dijalankan ulang: tidak dobel & akun tidak diubah
        $this->assertDatabaseHas('users', ['username' => 'budi', 'role' => 'bidang']);
        DB::table('users')->where('username', 'budi')->update(['role' => 'manajemen']);
        $this->assertSame(0, $this->artisan('dms:sinkron', ['file' => $this->dumpLama(), '--no-interaction' => true]));
        $this->assertSame(3, DB::table('sheet_data')->count());
        $this->assertSame('manajemen', DB::table('users')->where('username', 'budi')->value('role'));
    }

    public function test_file_salah_atau_tidak_ada(): void
    {
        $this->assertSame(1, $this->artisan('dms:sinkron', ['file' => '/tidak/ada.sql', '--no-interaction' => true]));
        $bukan = $this->tulis("CREATE TABLE `lain` (\n  `id` int\n);\nINSERT INTO `lain` VALUES (1);\n");
        $this->assertSame(1, $this->artisan('dms:sinkron', ['file' => $bukan, '--no-interaction' => true]));
    }
}
