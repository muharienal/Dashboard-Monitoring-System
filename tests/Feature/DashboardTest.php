<?php

namespace Tests\Feature;

class DashboardTest extends DmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $a = $this->user('admin');
        // Hari 1: k1 jalan 13 rusak 2 standby 9 (status JALAN), k2 perbaikan 16 (RUSAK)
        $this->simpan($a, $this->anglu, '2026-10-01', [
            ['kendaraan_id' => $this->k1->id, 'jalan' => 13, 'rusak' => 2],
            ['kendaraan_id' => $this->k2->id, 'perbaikan' => 16],
        ])->assertOk();
        // Hari 2: k1 semua standby, k2 pm 4 (STANDBY)
        $this->simpan($a, $this->anglu, '2026-10-02', [
            ['kendaraan_id' => $this->k1->id, 'jalan' => 0],
            ['kendaraan_id' => $this->k2->id, 'pm' => 4],
        ])->assertOk();
        // Bidang lain, bulan lain
        $this->simpan($a, $this->wil2, '2026-09-15', [['kendaraan_id' => $this->k3->id, 'jalan' => 24]])->assertOk();
    }

    public function test_availability_rate_rumus_tableau(): void
    {
        $r = $this->actingAs($this->user('manajemen'))->getJson(route('api.dashboard.availability').'?tahun=2026&bulan=10&bidang=angkutan-luar')->assertOk();
        // jalan 13, standby 9+8+24+20=61, rusak 2, perbaikan 16, pm 4 -> total 96
        $r->assertJsonPath('total.jam', ['JALAN' => 13, 'STANDBY' => 61, 'RUSAK' => 2, 'PERBAIKAN' => 16, 'PM' => 4]);
        $this->assertEquals(round(74 / 96 * 100, 2), $r->json('total.availability_rate'));
        // Utilisasi: 1 dari 4 unit-hari berstatus JALAN
        $this->assertEquals(25, $r->json('total.utilisasi'));
        $r->assertJsonPath('jenis.0.jenis', 'DUMP TRUK B3');
    }

    public function test_utilitas_rata_rata_per_hari(): void
    {
        $r = $this->actingAs($this->user('manajemen'))->getJson(route('api.dashboard.summary', $this->anglu).'?tahun=2026&bulan=10')->assertOk();
        // 2 hari: jalan 1 unit-hari, standby 2, rusak 1 -> rata2 0.5 / 1 / 0.5
        $r->assertJsonPath('utilitas_bulanan.0.periode', '2026-10');
        $this->assertEquals([0.5, 1, 0.5], [$r->json('utilitas_bulanan.0.jalan'), $r->json('utilitas_bulanan.0.standby'), $r->json('utilitas_bulanan.0.rusak')]);
        // Tren per hari memuat semua tanggal di bulan itu (31), termasuk yang belum ada datanya (tanpa tanda kelengkapan).
        $r->assertJsonPath('tren_mode', 'hari')->assertJsonCount(31, 'tren');
        $tren = collect($r->json('tren'));
        $this->assertSame(2, $tren->where('unit_hari', '>', 0)->count());
        $this->assertTrue($tren->every(fn ($t) => ! array_key_exists('lengkap', $t)));
        $this->assertNull($tren->firstWhere('unit_hari', 0)['jalan']);
        $r->assertJsonPath('maintenance.0.nomor', 'W 2 AA')->assertJsonPath('maintenance.0.perbaikan', 16);
        $this->assertEquals(8, $r->json('maintenance.0.rata_rata'));
    }

    public function test_detail_report_crosstab(): void
    {
        $r = $this->actingAs($this->user('manajemen'))->getJson(route('api.dashboard.report').'?tahun=2026&bulan=10&bidang=angkutan-luar')->assertOk();
        $r->assertJsonPath('report.mode', 'hari')->assertJsonCount(31, 'report.kolom')->assertJsonCount(2, 'report.unit');
        $r->assertJsonPath('report.unit.0.nilai.JALAN.1', 13)->assertJsonPath('report.unit.0.nilai.STANDBY.2', 24);
        // Semua bulan -> kolom per bulan
        $r2 = $this->actingAs($this->user('manajemen'))->getJson(route('api.dashboard.report').'?tahun=2026&bulan=')->assertOk();
        $r2->assertJsonPath('report.mode', 'bulan')->assertJsonPath('report.kolom', ['2026-09', '2026-10']);
    }

    public function test_filter_tanggal_dan_maintenance_per_bidang(): void
    {
        $r = $this->actingAs($this->user('manajemen'))->getJson(route('api.dashboard.availability').'?tahun=2026&bulan=10&hari=2')->assertOk();
        $r->assertJsonPath('total.unit_hari', 2)->assertJsonPath('total.jam.PM', 4);
        $m = $this->actingAs($this->user('manajemen'))->getJson(route('api.dashboard.maintenance').'?tahun=2026&bulan=10')->assertOk();
        $this->assertCount(5, $m->json('bidang'));
        $this->assertSame(16, $m->json('bidang.0.data.0.perbaikan'));
    }

    public function test_utilisasi_dan_rekap(): void
    {
        $r = $this->actingAs($this->user('manajemen'))->getJson(route('api.dashboard.utilisasi').'?tahun=2026&bulan=')->assertOk();
        $r->assertJsonCount(2, 'jenis');
        $g = $this->actingAs($this->user('manajemen'))->getJson(route('api.dashboard.general').'?tahun=2026&bulan=')->assertOk();
        $g->assertJsonPath('rekap.0.bidang', 'Angkutan Luar')->assertJsonPath('rekap.0.total_jam', 96);
        $this->actingAs($this->user('manajemen'))->getJson(route('beranda.data').'?tahun=2026&bulan=10')->assertOk()->assertJsonCount(5, 'bidang');
    }

    public function test_versi_berubah_saat_data_diinput(): void
    {
        $m = $this->user('admin');
        $v1 = $this->actingAs($m)->getJson(route('version'))->json('version');
        $this->simpan($m, $this->anglu, '2026-10-03', [['kendaraan_id' => $this->k1->id, 'jalan' => 3]])->assertOk();
        $v2 = $this->actingAs($m)->getJson(route('version'))->json('version');
        $this->assertNotSame($v1, $v2);
    }

    public function test_semua_halaman_dashboard_tampil(): void
    {
        $u = $this->user('manajemen');
        foreach (['beranda', 'dashboard.utilisasi', 'dashboard.availability', 'dashboard.maintenance', 'dashboard.laporan', 'profil'] as $r) {
            $this->actingAs($u)->get(route($r))->assertOk();
        }
        $this->actingAs($u)->get(route('dashboard.summary', $this->anglu))->assertOk()->assertSee('Angkutan Luar')->assertSee('Laporan detail');
        $this->actingAs($u)->get(route('dashboard.laporan', ['tab' => 'detail']))->assertOk()->assertSee('Detail per kendaraan');
        $this->actingAs($u)->get('/dashboard/detail-report?bidang=angkutan-luar')->assertRedirect(route('dashboard.laporan', ['tab' => 'detail', 'bidang' => 'angkutan-luar']));
        $this->actingAs($u)->get('/dashboard/general-report')->assertRedirect(route('dashboard.laporan', ['tab' => 'rekap']));
        $a = $this->user('admin');
        $this->actingAs($a)->get(route('master.kendaraan'))->assertOk()->assertSee('W 1 AA');
        $this->actingAs($a)->get(route('akun.index'))->assertOk();
        $this->actingAs($a)->get(route('input.show', $this->anglu))->assertOk();
    }
}
