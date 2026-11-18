<?php

namespace Tests\Feature;

use App\Services\DashboardService;
use App\Support\Filter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Jumlah unit = seluruh unit yang beroperasi pada periode itu (data master), termasuk yang Standby / belum ada datanya. */
class JumlahUnitTest extends DmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 10:00:00');
        DB::table('kendaraan')->update(['aktif_sejak' => '2026-01-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_unit_tanpa_data_tetap_dihitung_dan_mengikuti_periode_aktif(): void
    {
        $svc = app(DashboardService::class);
        // Hanya k1 punya data; k2 (Standby, belum ada baris) tetap terhitung.
        $this->simpan($this->user('bidang'), $this->anglu, '2026-10-05', [['kendaraan_id' => $this->k1->id, 'jalan' => 10, 'rusak' => 0]])->assertOk();
        $this->assertSame(2, $svc->availability(new Filter(2026, 10, null, $this->anglu))['unit']);
        $this->assertSame(3, $svc->availability(new Filter(2026, 10))['unit']);

        // Mulai beroperasi 15 Nov → belum dihitung di Oktober; nonaktif sejak 1 Okt → tidak dihitung di Oktober, masih di September.
        $this->k2->update(['aktif_sejak' => '2026-11-15']);
        $this->k3->update(['is_active' => false, 'nonaktif_sejak' => '2026-10-01']);
        $this->assertSame(1, $svc->availability(new Filter(2026, 10))['unit']);
        $this->assertSame(2, $svc->availability(new Filter(2026, 9))['unit']);
        $this->assertSame(1, $svc->jumlahUnit(new Filter(2026, 10, null, $this->anglu)));

        // Per jenis & per bidang di Beranda memakai definisi yang sama.
        $jenis = collect($svc->availabilityPerJenis(new Filter(2026, 10, null, $this->anglu)))->keyBy('jenis');
        $this->assertSame(1, $jenis[$this->k1->jenis->nama]['unit']);
        $d = $this->actingAs($this->user('manajemen'))->getJson(route('beranda.data', ['tahun' => 2026, 'bulan' => 9]))->json();
        $b = collect($d['bidang'])->keyBy('kode');
        $this->assertSame([1, 1], [$b['angkutan-luar']['unit'], $b['alber-wil-2']['unit']]); // k2 baru beroperasi November
        $this->assertSame(2, $d['availability']['unit']);
    }
}
