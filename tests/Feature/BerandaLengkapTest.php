<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

/** Beranda untuk manajemen: tombol "?" di setiap judul, data ringkasan, peringkat, kelengkapan. */
class BerandaLengkapTest extends DmsTestCase
{
    public function test_tombol_penjelasan_hanya_di_angka_penting(): void
    {
        $html = $this->actingAs($this->user('manajemen'))->get(route('beranda'))->assertOk()->getContent();
        // Tombol "?" hanya untuk angka yang perlu penjelasan, bukan di setiap judul.
        foreach (['Availability', 'Utilisasi', 'Jumlah unit', 'Jumlah unit per hari'] as $judul) {
            $this->assertStringContainsString('aria-label="Penjelasan: '.$judul.'"', $html, $judul);
        }
        $this->assertSame(4, substr_count($html, 'class="info-q'));
        // Kelengkapan input dihapus: unit yang memang Standby tidak boleh dianggap belum diisi.
        $this->assertStringNotContainsString('Kelengkapan', $html);
        $this->assertStringNotContainsString('Arti istilah', $html);
        $this->assertStringNotContainsString('Penjelasan: Pengingat input', $html);
        $this->assertStringContainsString('id="rkIsi"', $html); // kartu Ringkasan kembali
        $this->assertStringContainsString('id="modalUnduh"', $html);
    }

    public function test_data_beranda_lengkap(): void
    {
        $tgl = now()->startOfMonth()->toDateString();
        foreach ([[$this->k1, 10, 6], [$this->k2, 0, 24], [$this->k3, 8, 0]] as [$k, $jalan, $perb]) {
            DB::table('sheet_data')->insert(['tanggal' => $tgl, 'kendaraan_id' => $k->id, 'bidang_id' => $k->jenis->bidang_id, 'jenis_kendaraan_id' => $k->jenis_kendaraan_id,
                'jalan' => $jalan, 'standby' => 24 - $jalan - $perb, 'rusak' => 0, 'perbaikan' => $perb, 'pm' => 0, 'total_jam_kerja' => 24,
                'availability_rate' => (24 - $perb) / 24 * 100, 'utilitas_jalan' => $jalan > $perb ? 1 : 0, 'utilitas_standby' => 0, 'utilitas_rusak' => $jalan > $perb ? 0 : 1,
                'created_at' => now(), 'updated_at' => now()]);
        }
        $d = $this->actingAs($this->user('manajemen'))->getJson(route('beranda.data', ['tahun' => now()->year, 'bulan' => now()->month]))->assertOk()->json();
        $this->assertSame(['W 2 AA', 'W 1 AA'], array_column($d['perbaikan_top'], 'nomor'));
        $this->assertSame(2, $d['perbaikan_unit']);
        $this->assertSame('DUMP TRUK B3', $d['jenis_terendah'][0]['jenis']);
        $this->assertSame(2, $d['jenis_jumlah']);
        $this->assertArrayNotHasKey('kelengkapan_periode', $d);
    }
}
