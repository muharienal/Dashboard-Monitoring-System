<?php

namespace Tests\Feature;

use App\Support\Filter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Laporan PDF & Excel: satu bulan / beberapa bulan, semua bidang / satu bidang, petugas bidang terkunci. */
class LaporanRentangTest extends DmsTestCase
{
    private function isi(int $kendaraanId, int $bidangId, int $jenisId, string $tgl, int $jalan, int $perbaikan = 0): void
    {
        DB::table('sheet_data')->insert(['tanggal' => $tgl, 'kendaraan_id' => $kendaraanId, 'bidang_id' => $bidangId, 'jenis_kendaraan_id' => $jenisId,
            'jalan' => $jalan, 'standby' => 24 - $jalan - $perbaikan, 'rusak' => 0, 'perbaikan' => $perbaikan, 'pm' => 0, 'total_jam_kerja' => 24,
            'availability_rate' => (24 - $perbaikan) / 24 * 100, 'utilitas_jalan' => $jalan > 4 ? 1 : 0, 'utilitas_standby' => $jalan > 4 ? 0 : 1, 'utilitas_rusak' => 0,
            'created_at' => now(), 'updated_at' => now()]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->isi($this->k1->id, $this->anglu->id, $this->k1->jenis_kendaraan_id, '2026-08-05', 10, 2);
        $this->isi($this->k1->id, $this->anglu->id, $this->k1->jenis_kendaraan_id, '2026-09-05', 10, 4);
        $this->isi($this->k3->id, $this->wil2->id, $this->k3->jenis_kendaraan_id, '2026-09-06', 8, 6);
        $this->isi($this->k1->id, $this->anglu->id, $this->k1->jenis_kendaraan_id, '2026-10-05', 10);
    }

    public function test_filter_rentang_bulan(): void
    {
        $f = Filter::fromRequest(Request::create('/', 'GET', ['dari' => '2026-08', 'ke' => '2026-09']));
        $this->assertSame(['2026-08-01', '2026-09-30'], [$f->mulai, $f->sampai]);
        $this->assertSame('Agustus – September 2026', $f->label());
        // Terbalik ditukar, satu bulan = filter bulan biasa, lintas tahun, batas 24 bulan, nilai aneh diabaikan
        $this->assertSame('2026-08-01', Filter::fromRequest(Request::create('/', 'GET', ['dari' => '2026-09', 'ke' => '2026-08']))->mulai);
        $satu = Filter::fromRequest(Request::create('/', 'GET', ['dari' => '2026-09', 'ke' => '2026-09']));
        $this->assertSame([2026, 9, null], [$satu->tahun, $satu->bulan, $satu->mulai]);
        $this->assertSame('November 2025 – Januari 2026', Filter::fromRequest(Request::create('/', 'GET', ['dari' => '2025-11', 'ke' => '2026-01']))->label());
        $this->assertSame('2024-11-01', Filter::fromRequest(Request::create('/', 'GET', ['dari' => '2020-01', 'ke' => '2026-10']))->mulai);
        $this->assertNull(Filter::fromRequest(Request::create('/', 'GET', ['dari' => '2026-13', 'ke' => 'x']))->mulai);
    }

    public function test_pdf_beberapa_bulan_dan_satu_bidang(): void
    {
        $m = $this->user('manajemen');
        $r = $this->actingAs($m)->get(route('laporan.bulanan', ['dari' => '2026-08', 'ke' => '2026-10']))->assertOk()
            ->assertSee('LAPORAN OPERASIONAL KENDARAAN')->assertSee('Agustus – Oktober 2026')->assertSee('Per bulan')->assertSee('Semua bidang')
            ->assertSee('Per bidang');
        $this->assertSame(4, $r->viewData('total')['unit_hari']);
        $this->assertCount(3, $r->viewData('perBulan'));
        $this->assertSame(12, $r->viewData('total')['jam']['PERBAIKAN']);
        // Satu bidang: tabel per bidang tidak diulang, angka hanya bidang itu
        $r = $this->actingAs($m)->get(route('laporan.bulanan', ['dari' => '2026-08', 'ke' => '2026-10', 'bidang' => 'alber-wil-2']))->assertOk()
            ->assertSee('Bidang Alat Berat Wilayah 2')->assertDontSee('Per bidang</h2>', false);
        $this->assertSame(6, $r->viewData('total')['jam']['PERBAIKAN']);
        // Satu bulan tetap "Laporan Bulanan" (parameter lama tahun/bulan juga masih jalan)
        $this->actingAs($m)->get(route('laporan.bulanan', ['tahun' => 2026, 'bulan' => 9]))->assertOk()->assertSee('LAPORAN BULANAN')->assertDontSee('Per bulan</h2>', false);
    }

    public function test_petugas_bidang_terkunci_ke_bidangnya(): void
    {
        $p = $this->user('bidang', $this->anglu->id);
        // PDF: walau meminta bidang lain / semua, yang tampil tetap bidangnya
        $r = $this->actingAs($p)->get(route('laporan.bulanan', ['dari' => '2026-08', 'ke' => '2026-10', 'bidang' => 'alber-wil-2']))->assertOk()
            ->assertSee('Bidang Angkutan Luar');
        $this->assertSame(6, $r->viewData('total')['jam']['PERBAIKAN']);
        $this->actingAs($p)->get(route('beranda'))->assertSee('Sesuai bidang akun Anda.');
        // Excel (endpoint data dengan ekspor=1): dikunci di server
        $d = $this->actingAs($p)->getJson(route('api.dashboard.maintenance', ['dari' => '2026-08', 'ke' => '2026-10', 'ekspor' => 1, 'bidang' => 'alber-wil-2']))->assertOk()->json();
        $this->assertSame(['angkutan-luar'], array_column($d['bidang'], 'kode'));
        $this->assertSame('Angkutan Luar', $d['filter']['bidang_nama']);
        // Admin & manajemen: bebas (semua atau satu bidang)
        $d = $this->actingAs($this->user('admin'))->getJson(route('api.dashboard.maintenance', ['dari' => '2026-08', 'ke' => '2026-10', 'ekspor' => 1]))->json();
        $this->assertCount(5, $d['bidang']);
        $this->assertSame(12, array_sum(array_map(fn ($b) => array_sum(array_column($b['data'], 'perbaikan')), $d['bidang'])));
    }

    public function test_ekspor_rentang_untuk_semua_jenis_data(): void
    {
        $a = $this->user('admin');
        $q = ['dari' => '2026-08', 'ke' => '2026-10', 'ekspor' => 1];
        $this->assertSame(4, $this->actingAs($a)->getJson(route('api.dashboard.availability', $q))->json('total.unit_hari'));
        $this->assertNotEmpty($this->actingAs($a)->getJson(route('api.dashboard.utilisasi', $q))->json('jenis'));
        $this->assertSame(4, $this->actingAs($a)->getJson(route('api.dashboard.general', $q))->json('total.unit_hari'));
        $rep = $this->actingAs($a)->getJson(route('api.dashboard.report', $q))->json('report');
        $this->assertSame('bulan', $rep['mode']);
        $this->assertSame(['2026-08', '2026-09', '2026-10'], $rep['kolom']);
        $this->assertSame('Agustus – Oktober 2026', $this->actingAs($a)->getJson(route('api.dashboard.general', $q))->json('filter.label'));
    }
}
