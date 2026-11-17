<?php

namespace Tests\Feature;

use App\Models\Kendaraan;
use App\Services\DashboardService;
use Illuminate\Support\Facades\DB;

/** Deteksi isian "kosong" (tak ada unit Jalan/Rusak) dan perbandingan KPI di Dashboard Bidang. */
class FiturBaru3Test extends DmsTestCase
{
    private function baris(Kendaraan $k, int $bidangId, string $tgl, int $jalan = 0): void
    {
        DB::table('sheet_data')->insert([
            'tanggal' => $tgl, 'kendaraan_id' => $k->id, 'bidang_id' => $bidangId, 'jenis_kendaraan_id' => $k->jenis_kendaraan_id,
            'jalan' => $jalan, 'standby' => 24 - $jalan, 'rusak' => 0, 'perbaikan' => 0, 'pm' => 0, 'total_jam_kerja' => 24,
            'availability_rate' => 100, 'utilitas_jalan' => $jalan > 4 ? 1 : 0, 'utilitas_standby' => $jalan > 4 ? 0 : 1, 'utilitas_rusak' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function kl(array $d, int $hari, string $kode): array
    {
        return collect($d['kelengkapan'][$hari]['bidang'])->firstWhere('kode', $kode);
    }

    public function test_beranda_menandai_bidang_tanpa_unit_jalan_atau_rusak(): void
    {
        $admin = $this->user('admin');
        $hariIni = now()->toDateString();
        // Disimpan tanpa mengisi apa pun: semua unit Standby 24 jam.
        $this->simpan($admin, $this->anglu, $hariIni, [['kendaraan_id' => $this->k1->id, 'jalan' => 0], ['kendaraan_id' => $this->k2->id, 'jalan' => 0]])->assertOk();
        $b = $this->kl($this->actingAs($admin)->getJson(route('beranda.data'))->json(), 1, 'angkutan-luar');
        $this->assertSame([0, 2, 2, true], [$b['belum'], $b['terisi'], $b['tanpa_jalan_rusak'], $b['curiga_kosong']]);

        // Satu unit diisi jam Jalan → tidak lagi dianggap kosong.
        $this->simpan($admin, $this->anglu, $hariIni, [['kendaraan_id' => $this->k1->id, 'jalan' => 8]])->assertOk();
        $b = $this->kl($this->actingAs($admin)->getJson(route('beranda.data'))->json(), 1, 'angkutan-luar');
        $this->assertSame([1, false], [$b['tanpa_jalan_rusak'], $b['curiga_kosong']]);

        // Belum ada data sama sekali tidak dianggap "kosong" (itu sudah tercakup sebagai "belum").
        $w = $this->kl($this->actingAs($admin)->getJson(route('beranda.data'))->json(), 1, 'alber-wil-2');
        $this->assertSame([0, false], [$w['terisi'], $w['curiga_kosong']]);
    }

    public function test_hanya_perbaikan_pm_diisi_tetap_terdeteksi(): void
    {
        // Pola data nyata: petugas Pemeliharaan mengisi Perbaikan/PM, kolom Jalan & Rusak tidak diisi siapa pun.
        $pemeliharaan = $this->user('pemeliharaan');
        $this->simpan($pemeliharaan, $this->anglu, now()->toDateString(), [['kendaraan_id' => $this->k1->id, 'pm' => 2], ['kendaraan_id' => $this->k2->id, 'perbaikan' => 0]])->assertOk();
        $b = $this->kl($this->actingAs($this->user('admin'))->getJson(route('beranda.data'))->json(), 1, 'angkutan-luar');
        $this->assertTrue($b['curiga_kosong']);
        // Begitu satu unit tercatat Rusak, tanda hilang.
        $this->simpan($this->user('bidang', $this->anglu->id), $this->anglu, now()->toDateString(), [['kendaraan_id' => $this->k2->id, 'rusak' => 3]])->assertOk();
        $b = $this->kl($this->actingAs($this->user('admin'))->getJson(route('beranda.data'))->json(), 1, 'angkutan-luar');
        $this->assertFalse($b['curiga_kosong']);
    }

    public function test_isian_kosong_bulanan_dan_catatan_laporan_pdf(): void
    {
        $this->baris($this->k1, $this->anglu->id, '2026-10-01');
        $this->baris($this->k2, $this->anglu->id, '2026-10-01');
        $this->baris($this->k1, $this->anglu->id, '2026-10-02', 8);
        $this->baris($this->k2, $this->anglu->id, '2026-10-02');
        $this->baris($this->k1, $this->anglu->id, '2026-10-03');

        $k = app(DashboardService::class)->isianKosongBulan(2026, 10);
        $this->assertSame(['tanggal_data' => 3, 'tanggal_kosong' => 2, 'contoh' => [1, 3]], $k['angkutan-luar']);
        $this->assertSame(0, $k['alber-wil-2']['tanggal_data']);

        $this->actingAs($this->user('manajemen'))->get(route('laporan.bulanan', ['tahun' => 2026, 'bulan' => 10]))->assertOk()
            ->assertSee('Tidak ada satu unit pun yang tercatat Jalan atau Rusak di', false)
            ->assertSee('Angkutan Luar (2 dari 3 tanggal, mis. tanggal 1, 3)', false)
            ->assertSee('<sup class="tanda"', false);
        // Bulan tanpa isian kosong: tidak ada tanda.
        $this->actingAs($this->user('manajemen'))->get(route('laporan.bulanan', ['tahun' => 2026, 'bulan' => 9]))->assertOk()
            ->assertDontSee('<sup class="tanda"', false);
    }

    public function test_dashboard_bidang_membandingkan_dengan_bulan_lalu_bidang_yang_sama(): void
    {
        $this->baris($this->k1, $this->anglu->id, '2025-12-10', 8);
        $this->baris($this->k3, $this->wil2->id, '2025-12-10', 8);
        $this->baris($this->k3, $this->wil2->id, '2025-12-11', 8);

        $p = $this->actingAs($this->user('manajemen'))->getJson(route('api.dashboard.summary', $this->anglu).'?tahun=2026&bulan=1')->assertOk()->json('pembanding');
        $this->assertSame('Desember 2025', $p['label']);
        $this->assertSame(1, $p['availability']['unit_hari'], 'hanya data Angkutan Luar yang dibandingkan');
        $this->assertEquals(1, $p['availability']['avg_jalan']);

        $w = $this->actingAs($this->user('manajemen'))->getJson(route('api.dashboard.summary', $this->wil2).'?tahun=2026&bulan=1')->json('pembanding');
        $this->assertSame(2, $w['availability']['unit_hari']);
    }
}
