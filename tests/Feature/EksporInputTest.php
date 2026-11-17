<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

/** Ekspor Excel di halaman Input: semua kendaraan per tanggal (terisi maupun belum), hanya bidang itu, hak akses sama dengan input. */
class EksporInputTest extends DmsTestCase
{
    private function isi(int $kid, int $bid, int $jid, string $tgl, int $jalan): void
    {
        DB::table('sheet_data')->insert(['tanggal' => $tgl, 'kendaraan_id' => $kid, 'bidang_id' => $bid, 'jenis_kendaraan_id' => $jid,
            'jalan' => $jalan, 'standby' => 24 - $jalan, 'rusak' => 0, 'perbaikan' => 0, 'pm' => 0, 'total_jam_kerja' => 24, 'availability_rate' => 100,
            'utilitas_jalan' => $jalan > 4 ? 1 : 0, 'utilitas_standby' => $jalan > 4 ? 0 : 1, 'utilitas_rusak' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_ekspor_rentang_tanggal(): void
    {
        $this->isi($this->k1->id, $this->anglu->id, $this->k1->jenis_kendaraan_id, '2026-10-01', 10);
        $this->isi($this->k2->id, $this->anglu->id, $this->k2->jenis_kendaraan_id, '2026-10-02', 8);
        $this->isi($this->k1->id, $this->anglu->id, $this->k1->jenis_kendaraan_id, '2026-10-05', 6);
        $this->isi($this->k3->id, $this->wil2->id, $this->k3->jenis_kendaraan_id, '2026-10-02', 12);
        $admin = $this->user('admin');
        $d = $this->actingAs($admin)->getJson(route('input.ekspor', ['bidang' => $this->anglu, 'dari' => '2026-10-01', 'sampai' => '2026-10-02']))->assertOk()->json();
        $this->assertSame(2, $d['hari']);
        $this->assertSame(2, $d['terisi']);
        $this->assertArrayNotHasKey('oleh', array_flip($d['kolom'])); // kolom "Disimpan oleh" dihapus
        // Semua kendaraan bidang per tanggal ikut, yang belum diisi bernilai kosong; bidang lain tidak ikut.
        $ringkas = array_map(fn ($r) => [$r[0], $r[2], $r[3]], $d['rows']);
        $this->assertSame([['2026-10-01', 'W 1 AA', 10], ['2026-10-01', 'W 2 AA', null], ['2026-10-02', 'W 1 AA', null], ['2026-10-02', 'W 2 AA', 8]], $ringkas);
        $this->assertNull($d['rows'][1][9]);
        $this->assertSame(100.0, (float) $d['rows'][0][9]);
        $bulan = $this->actingAs($admin)->getJson(route('input.ekspor', ['bidang' => $this->anglu, 'dari' => '2026-10-01', 'sampai' => '2026-10-31']))->json();
        $this->assertCount(62, $bulan['rows']);
        $this->assertSame(3, $bulan['terisi']);
        // Periode aktif kendaraan dihormati: W 2 AA baru aktif 3 Oktober, nonaktif mulai 5 Oktober.
        $this->k2->update(['aktif_sejak' => '2026-10-03', 'nonaktif_sejak' => '2026-10-05']);
        $r = $this->actingAs($admin)->getJson(route('input.ekspor', ['bidang' => $this->anglu, 'dari' => '2026-10-01', 'sampai' => '2026-10-06']))->json('rows');
        $w2 = array_values(array_map(fn ($x) => $x[0], array_filter($r, fn ($x) => $x[2] === 'W 2 AA')));
        $this->assertSame(['2026-10-02', '2026-10-03', '2026-10-04'], $w2); // 2 Okt tetap ikut karena sudah punya data
        // Validasi: terbalik, masa depan, terlalu panjang, kosong
        $this->actingAs($admin)->getJson(route('input.ekspor', ['bidang' => $this->anglu, 'dari' => '2026-10-05', 'sampai' => '2026-10-01']))->assertStatus(422);
        $this->actingAs($admin)->getJson(route('input.ekspor', ['bidang' => $this->anglu, 'dari' => '2026-10-01', 'sampai' => now()->addDay()->toDateString()]))->assertStatus(422);
        $this->actingAs($admin)->getJson(route('input.ekspor', ['bidang' => $this->anglu, 'dari' => '2024-01-01', 'sampai' => '2025-12-31']))->assertStatus(422);
        $this->actingAs($admin)->getJson(route('input.ekspor', ['bidang' => $this->anglu]))->assertStatus(422);
    }

    public function test_hak_akses_sama_dengan_input(): void
    {
        $q = ['dari' => '2026-10-01', 'sampai' => '2026-10-02'];
        $this->actingAs($this->user('bidang', $this->anglu->id))->getJson(route('input.ekspor', ['bidang' => $this->anglu] + $q))->assertOk();
        $this->actingAs($this->user('bidang', $this->anglu->id))->getJson(route('input.ekspor', ['bidang' => $this->wil2] + $q))->assertForbidden();
        $this->actingAs($this->user('pemeliharaan'))->getJson(route('input.ekspor', ['bidang' => $this->wil2] + $q))->assertOk();
        $this->actingAs($this->user('manajemen'))->getJson(route('input.ekspor', ['bidang' => $this->anglu] + $q))->assertForbidden();
        $html = $this->actingAs($this->user('admin'))->get(route('input.show', $this->anglu))->assertOk()->getContent();
        $this->assertStringContainsString('Ekspor Excel', $html);
        $this->assertStringContainsString('id="modalEkspor"', $html);
        $this->assertStringContainsString('class="btn btn-sm btn-outline-secondary" id="btnExport"', $html);
    }
}
