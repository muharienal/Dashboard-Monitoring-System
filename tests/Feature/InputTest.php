<?php

namespace Tests\Feature;

use App\Models\SheetData;

class InputTest extends DmsTestCase
{
    private string $tgl = '2026-10-01';

    public function test_grid_menampilkan_kendaraan_aktif_default_standby_24(): void
    {
        $u = $this->user('bidang');
        $r = $this->actingAs($u)->getJson(route('input.data', $this->anglu).'?tanggal='.$this->tgl)->assertOk();
        $r->assertJsonCount(2, 'rows')->assertJsonPath('rows.0.standby', 24)->assertJsonPath('kolom_edit', ['jalan', 'rusak']);
    }

    public function test_tanggal_masa_depan_ditolak(): void
    {
        $u = $this->user('bidang');
        $this->actingAs($u)->getJson(route('input.data', $this->anglu).'?tanggal='.now()->addDay()->toDateString())->assertStatus(422);
    }

    public function test_simpan_menghitung_semua_kolom_di_server(): void
    {
        $u = $this->user('bidang');
        $this->simpan($u, $this->anglu, $this->tgl, [['kendaraan_id' => $this->k1->id, 'jalan' => 12, 'rusak' => 2]])
            ->assertOk()->assertJsonPath('dibuat', 1);
        $s = SheetData::first();
        $this->assertSame([12, 10, 2, 0, 0, 24], [$s->jalan, $s->standby, $s->rusak, $s->perbaikan, $s->pm, $s->total_jam_kerja]);
        $this->assertEquals(91.67, $s->availability_rate);
        $this->assertSame(1, $s->utilitas_jalan);
        $this->assertSame($u->id, $s->created_by);
        $this->assertSame($this->anglu->id, $s->bidang_id);
    }

    public function test_role_bidang_tidak_bisa_mengubah_perbaikan_pm(): void
    {
        $u = $this->user('bidang');
        $this->simpan($u, $this->anglu, $this->tgl, [['kendaraan_id' => $this->k1->id, 'jalan' => 5, 'perbaikan' => 9, 'pm' => 3]])->assertOk();
        $s = SheetData::first();
        $this->assertSame([5, 0, 0, 19], [$s->jalan, $s->perbaikan, $s->pm, $s->standby]);
    }

    public function test_pemeliharaan_dan_bidang_mengisi_kolom_masing_masing_tanpa_saling_timpa(): void
    {
        $b = $this->user('bidang');
        $p = $this->user('pemeliharaan');
        $this->simpan($b, $this->anglu, $this->tgl, [['kendaraan_id' => $this->k1->id, 'jalan' => 8, 'rusak' => 0]])->assertOk();
        // Pemeliharaan menyimpan dari halaman yang dimuat SEBELUM bidang menyimpan (jalan=0 di browsernya)
        $this->simpan($p, $this->anglu, $this->tgl, [['kendaraan_id' => $this->k1->id, 'perbaikan' => 4, 'pm' => 2, 'jalan' => 0]])->assertOk();
        $s = SheetData::first();
        $this->assertSame([8, 4, 2, 10], [$s->jalan, $s->perbaikan, $s->pm, $s->standby]);
    }

    public function test_total_lebih_24_setelah_digabung_ditolak_semua(): void
    {
        $b = $this->user('bidang');
        $p = $this->user('pemeliharaan');
        $this->simpan($p, $this->anglu, $this->tgl, [['kendaraan_id' => $this->k1->id, 'perbaikan' => 20]])->assertOk();
        $this->simpan($b, $this->anglu, $this->tgl, [
            ['kendaraan_id' => $this->k2->id, 'jalan' => 3],
            ['kendaraan_id' => $this->k1->id, 'jalan' => 6],
        ])->assertStatus(422)->assertJsonPath('errors.0.nomor', 'W 1 AA');
        $this->assertSame(1, SheetData::count()); // k2 tidak ikut tersimpan (transaksi)
    }

    public function test_konflik_nilai_yang_sama_diubah_dua_orang(): void
    {
        $a = $this->user('bidang');
        $b = $this->user('bidang');
        $this->simpan($a, $this->anglu, $this->tgl, [['kendaraan_id' => $this->k1->id, 'jalan' => 8, 'asal' => ['jalan' => 0]]])->assertOk();
        $this->simpan($b, $this->anglu, $this->tgl, [['kendaraan_id' => $this->k1->id, 'jalan' => 3, 'asal' => ['jalan' => 0]]])
            ->assertStatus(409)->assertJsonPath('konflik.0.kolom', ['jalan']);
        $this->assertSame(8, SheetData::first()->jalan);
    }

    public function test_bidang_dengan_semua_kolom_terbuka(): void
    {
        $b = $this->user('bidang');
        $this->simpan($b, $this->wil2, $this->tgl, [['kendaraan_id' => $this->k3->id, 'jalan' => 2, 'perbaikan' => 5, 'pm' => 1]])->assertOk();
        $s = SheetData::first();
        $this->assertSame([2, 5, 1, 16], [$s->jalan, $s->perbaikan, $s->pm, $s->standby]);
    }

    public function test_kendaraan_bidang_lain_ditolak(): void
    {
        $b = $this->user('admin');
        $this->simpan($b, $this->anglu, $this->tgl, [['kendaraan_id' => $this->k3->id, 'jalan' => 2]])->assertStatus(422);
    }

    public function test_nilai_tidak_valid_ditolak_validasi(): void
    {
        $b = $this->user('admin');
        $this->simpan($b, $this->anglu, $this->tgl, [['kendaraan_id' => $this->k1->id, 'jalan' => 25]])->assertStatus(422);
        $this->simpan($b, $this->anglu, $this->tgl, [['kendaraan_id' => $this->k1->id, 'jalan' => -1]])->assertStatus(422);
        $this->assertSame(0, SheetData::count());
    }

    public function test_manajemen_tidak_bisa_simpan(): void
    {
        $this->simpan($this->user('manajemen'), $this->anglu, $this->tgl, [['kendaraan_id' => $this->k1->id, 'jalan' => 2]])->assertForbidden();
    }

    public function test_kendaraan_nonaktif_mulai_tanggal_tertentu(): void
    {
        $u = $this->user('admin');
        $this->simpan($u, $this->anglu, '2026-10-01', [['kendaraan_id' => $this->k1->id, 'jalan' => 1]])->assertOk();

        // Nonaktif mulai 3 Okt lewat halaman Kendaraan
        $this->actingAs($u)->patch(route('master.kendaraan.toggle', $this->k1), ['aksi' => 'nonaktif', 'nonaktif_sejak' => '2026-10-03', 'keterangan' => 'Dijual'])
            ->assertSessionHas('success');
        $this->k1->refresh();
        $this->assertFalse($this->k1->is_active);
        $this->assertSame('Dijual', $this->k1->keterangan);

        $rows = fn ($tgl) => $this->actingAs($u)->getJson(route('input.data', $this->anglu).'?tanggal='.$tgl)->json('rows');
        $this->assertCount(2, $rows('2026-10-02')); // sebelum tanggal nonaktif: masih bisa diisi (data terlewat)
        $this->assertCount(1, $rows('2026-10-03')); // mulai tanggal nonaktif: tidak muncul
        $this->simpan($u, $this->anglu, '2026-10-02', [['kendaraan_id' => $this->k1->id, 'jalan' => 2]])->assertOk();
        $this->simpan($u, $this->anglu, '2026-10-04', [['kendaraan_id' => $this->k1->id, 'jalan' => 2]])->assertStatus(422);

        // Riwayat tetap ada di laporan
        $rep = $this->actingAs($u)->getJson(route('api.dashboard.report').'?tahun=2026&bulan=10&bidang=angkutan-luar')->json('report.unit');
        $this->assertContains('W 1 AA', array_column($rep, 'nomor'));

        // Aktifkan kembali
        $this->actingAs($u)->patch(route('master.kendaraan.toggle', $this->k1), ['aksi' => 'aktif'])->assertSessionHas('success');
        $this->assertCount(2, $rows('2026-10-05'));
    }

    public function test_tanggal_nonaktif_tidak_boleh_di_masa_depan(): void
    {
        $u = $this->user('admin');
        $this->actingAs($u)->patch(route('master.kendaraan.toggle', $this->k1), ['aksi' => 'nonaktif', 'nonaktif_sejak' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('nonaktif_sejak');
        $this->assertTrue($this->k1->fresh()->is_active);
    }

    public function test_kendaraan_baru_tidak_muncul_di_tanggal_sebelum_beroperasi(): void
    {
        $u = $this->user('admin');
        $this->k2->update(['aktif_sejak' => '2026-10-10']);
        $this->assertCount(1, $this->actingAs($u)->getJson(route('input.data', $this->anglu).'?tanggal=2026-10-01')->json('rows'));
        $this->assertCount(2, $this->actingAs($u)->getJson(route('input.data', $this->anglu).'?tanggal=2026-10-10')->json('rows'));
        // Tidak bisa dibuatkan data (mis. standby palsu) sebelum mulai beroperasi
        $this->simpan($u, $this->anglu, '2026-10-01', [['kendaraan_id' => $this->k2->id, 'jalan' => 0]])->assertStatus(422);
    }

    public function test_master_kendaraan_duplikat_ditolak_dan_berriwayat_tidak_bisa_dihapus(): void
    {
        $a = $this->user('admin');
        $this->actingAs($a)->post(route('master.kendaraan.store'), ['jenis_kendaraan_id' => $this->k1->jenis_kendaraan_id, 'nomor_kendaraan' => ' w 1  aa ', 'aktif_sejak' => '2026-01-01'])
            ->assertSessionHasErrors('nomor_kendaraan');
        $this->simpan($a, $this->anglu, $this->tgl, [['kendaraan_id' => $this->k1->id, 'jalan' => 1]]);
        $this->actingAs($a)->delete(route('master.kendaraan.destroy', $this->k1))->assertSessionHas('error');
        $this->assertModelExists($this->k1);
    }
}
