<?php

namespace Tests\Feature;

use App\Models\SheetData;
use App\Services\DashboardService;
use App\Support\IsiOtomatis;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Data harian otomatis: kendaraan aktif otomatis punya data Standby 24 jam; disimpan atau tidak, data tetap ada. */
class IsiOtomatisTest extends DmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-05 09:00:00');
        DB::table('pengaturan')->where('kunci', 'isi_otomatis_sejak')->update(['nilai' => '2026-10-01']);
        DB::table('kendaraan')->update(['aktif_sejak' => '2026-09-01']);
        IsiOtomatis::lupakan();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_migrasi_mencatat_tanggal_mulai_awal_bulan(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('sheet_data', 'otomatis'));
        $this->assertSame('2026-10-01', IsiOtomatis::sejak());
    }

    public function test_semua_kendaraan_aktif_terisi_standby_24_dan_aman_diulang(): void
    {
        // Data yang sudah diisi petugas (dibuat lewat model, format tanggal sama seperti aplikasi) tidak boleh ditimpa.
        SheetData::create(\App\Support\JamKerja::hitung(10, 0, 0, 0) + ['tanggal' => '2026-10-02', 'kendaraan_id' => $this->k1->id,
            'bidang_id' => $this->anglu->id, 'jenis_kendaraan_id' => $this->k1->jenis_kendaraan_id]);

        $baru = IsiOtomatis::pastikan(true);
        $this->assertSame(3 * 5 - 1, $baru); // 3 kendaraan × 1–5 Okt, dikurangi 1 yang sudah diisi
        $this->assertSame(15, SheetData::count());
        $this->assertSame(0, IsiOtomatis::pastikan(true)); // diulang: tidak dobel

        $isi = SheetData::where('kendaraan_id', $this->k1->id)->whereDate('tanggal', '2026-10-02')->first();
        $this->assertSame([10, false], [$isi->jalan, $isi->otomatis]);
        $oto = SheetData::where('kendaraan_id', $this->k2->id)->whereDate('tanggal', '2026-10-05')->first();
        $this->assertSame([0, 24, 0, 0, 0, 24, true, 1], [$oto->jalan, $oto->standby, $oto->rusak, $oto->perbaikan, $oto->pm, $oto->total_jam_kerja, $oto->otomatis, $oto->utilitas_standby]);
        $this->assertEquals(100, $oto->availability_rate);
        $this->assertNull($oto->updated_by);
        $this->assertSame($this->anglu->id, $oto->bidang_id);
        // Tidak ada tanggal setelah hari ini / sebelum tanggal mulai.
        $this->assertSame(0, SheetData::where('tanggal', '>', '2026-10-05')->orWhere('tanggal', '<', '2026-10-01')->count());
    }

    public function test_mengikuti_periode_aktif_kendaraan(): void
    {
        $this->k2->update(['aktif_sejak' => '2026-10-03']);
        $this->k3->update(['is_active' => false, 'nonaktif_sejak' => '2026-10-04']);
        IsiOtomatis::pastikan(true);
        $tgl = fn ($k) => SheetData::where('kendaraan_id', $k->id)->orderBy('tanggal')->pluck('tanggal')->map->toDateString()->all();
        $this->assertSame(['2026-10-03', '2026-10-04', '2026-10-05'], $tgl($this->k2));
        $this->assertSame(['2026-10-01', '2026-10-02', '2026-10-03'], $tgl($this->k3));
    }

    public function test_otomatis_berjalan_saat_pengguna_membuka_dms(): void
    {
        $this->assertSame(0, SheetData::count());
        $this->actingAs($this->user('admin'))->get(route('beranda'))->assertOk();
        $this->assertSame(15, SheetData::count());
        // Tamu (belum login) tidak memicu apa pun.
        DB::table('sheet_data')->delete();
        IsiOtomatis::lupakan();
        auth()->logout();
        $this->get(route('login'))->assertOk();
        $this->assertSame(0, SheetData::count());
    }

    public function test_input_baris_otomatis_tampil_belum_diisi_dan_simpan_tanpa_ubah_tercatat(): void
    {
        IsiOtomatis::pastikan(true);
        $u = $this->user('bidang');
        $g = $this->actingAs($u)->getJson(route('input.data', $this->anglu).'?tanggal=2026-10-03')->assertOk()->json();
        $this->assertSame(0, $g['jumlah_tersimpan']);
        $this->assertFalse($g['rows'][0]['tersimpan']);
        $this->assertTrue($g['rows'][0]['otomatis']);
        $this->assertSame(24, $g['rows'][0]['standby']);
        $this->assertNull($g['terakhir']);

        // Simpan tanpa mengubah angka = petugas memastikan unit Standby; angka tetap, status jadi diisi.
        $this->simpan($u, $this->anglu, '2026-10-03', [['kendaraan_id' => $this->k1->id, 'jalan' => 0, 'rusak' => 0], ['kendaraan_id' => $this->k2->id, 'jalan' => 7, 'rusak' => 0]])
            ->assertOk()->assertJsonPath('dibuat', 2);
        $this->assertSame(3, SheetData::whereDate('tanggal', '2026-10-03')->count()); // tetap 3 kendaraan, tidak menambah baris
        $k1 = SheetData::where('kendaraan_id', $this->k1->id)->whereDate('tanggal', '2026-10-03')->first();
        $this->assertSame([24, false, $u->id, $u->id], [$k1->standby, $k1->otomatis, $k1->created_by, $k1->updated_by]);
        $this->assertSame(7, SheetData::where('kendaraan_id', $this->k2->id)->whereDate('tanggal', '2026-10-03')->value('jalan'));
        $this->assertSame(2, DB::table('riwayat_input')->where('aksi', 'buat')->count());

        $g = $this->actingAs($u)->getJson(route('input.data', $this->anglu).'?tanggal=2026-10-03')->json();
        $this->assertSame(2, $g['jumlah_tersimpan']);
        $this->assertTrue($g['rows'][0]['tersimpan']);
    }

    public function test_dashboard_menghitung_data_otomatis_tetapi_pengingat_tetap_menandai_belum_diisi(): void
    {
        IsiOtomatis::pastikan(true);
        $svc = app(DashboardService::class);
        $kl = collect($svc->kelengkapan(['2026-10-05'])[0]['bidang'])->keyBy('kode');
        $this->assertSame([2, 0, 2, false], [$kl['angkutan-luar']['unit'], $kl['angkutan-luar']['terisi'], $kl['angkutan-luar']['belum'], $kl['angkutan-luar']['curiga_kosong']]);

        $this->simpan($this->user('bidang'), $this->anglu, '2026-10-05', [['kendaraan_id' => $this->k1->id, 'jalan' => 12, 'rusak' => 0]])->assertOk();
        $kl = collect($svc->kelengkapan(['2026-10-05'])[0]['bidang'])->keyBy('kode');
        $this->assertSame([2, 2, 0], [$kl['angkutan-luar']['unit'], $kl['angkutan-luar']['terisi'], $kl['angkutan-luar']['belum']]);
        $this->assertSame([1, 0, 1], [$kl['alber-wil-2']['unit'], $kl['alber-wil-2']['terisi'], $kl['alber-wil-2']['belum']]);

        // Kelengkapan bulan: hanya tanggal yang sudah disimpan petugas yang dihitung terisi.
        $b = $svc->kelengkapanBulan(2026, 10)['angkutan-luar'];
        $this->assertSame([10, 2], [$b['seharusnya'], $b['terisi']]);

        // Angka dashboard memakai data otomatis (data sudah ada di database).
        $this->assertSame(15, DB::table('sheet_data')->count());
    }

    public function test_kendaraan_baru_langsung_terisi_dan_bisa_dihapus_bila_hanya_data_otomatis(): void
    {
        $admin = $this->user('admin');
        $jenis = $this->k1->jenis_kendaraan_id;
        $this->actingAs($admin)->post(route('master.kendaraan.store'), ['jenis_kendaraan_id' => $jenis, 'nomor_kendaraan' => 'W 9 ZZ', 'aktif_sejak' => '2026-10-04'])->assertRedirect();
        $baru = \App\Models\Kendaraan::where('nomor_kendaraan', 'W 9 ZZ')->firstOrFail();
        $this->assertSame(2, SheetData::where('kendaraan_id', $baru->id)->where('otomatis', true)->count());

        $this->actingAs($admin)->delete(route('master.kendaraan.destroy', $baru))->assertSessionHas('success');
        $this->assertNull(\App\Models\Kendaraan::find($baru->id));
        $this->assertSame(0, SheetData::where('kendaraan_id', $baru->id)->count());
    }

    public function test_nonaktif_menghapus_data_otomatis_setelah_tanggal_itu_tapi_data_petugas_tetap(): void
    {
        $admin = $this->user('admin');
        IsiOtomatis::pastikan(true);
        $this->simpan($this->user('bidang'), $this->anglu, '2026-10-04', [['kendaraan_id' => $this->k1->id, 'jalan' => 9, 'rusak' => 0]])->assertOk();
        $this->actingAs($admin)->patch(route('master.kendaraan.toggle', $this->k1), ['aksi' => 'nonaktif', 'nonaktif_sejak' => '2026-10-03'])->assertSessionHas('success');
        $tgl = SheetData::where('kendaraan_id', $this->k1->id)->orderBy('tanggal')->get()->map(fn ($s) => $s->tanggal->toDateString().($s->otomatis ? '' : '*'))->all();
        $this->assertSame(['2026-10-01', '2026-10-02', '2026-10-04*'], $tgl);

        // Kendaraan dengan data petugas tetap tidak bisa dihapus.
        $this->actingAs($admin)->delete(route('master.kendaraan.destroy', $this->k1))->assertSessionHas('error');
    }

    public function test_pindah_jenis_data_otomatis_ikut_jenis_baru(): void
    {
        IsiOtomatis::pastikan(true);
        $lain = \App\Models\JenisKendaraan::where('bidang_id', $this->anglu->id)->where('id', '!=', $this->k1->jenis_kendaraan_id)->firstOrFail();
        $this->actingAs($this->user('admin'))->put(route('master.kendaraan.update', $this->k1), [
            'jenis_kendaraan_id' => $lain->id, 'nomor_kendaraan' => 'W 1 AA', 'aktif_sejak' => '2026-09-01',
        ])->assertSessionHas('success');
        $this->assertSame([$lain->id], SheetData::where('kendaraan_id', $this->k1->id)->distinct()->pluck('jenis_kendaraan_id')->all());
        $this->assertSame(5, SheetData::where('kendaraan_id', $this->k1->id)->count());
    }

    public function test_ekspor_input_menandai_status_otomatis(): void
    {
        IsiOtomatis::pastikan(true);
        $u = $this->user('bidang');
        $this->simpan($u, $this->anglu, '2026-10-01', [['kendaraan_id' => $this->k1->id, 'jalan' => 5, 'rusak' => 0]])->assertOk();
        $d = $this->actingAs($u)->getJson(route('input.ekspor', ['bidang' => $this->anglu, 'dari' => '2026-10-01', 'sampai' => '2026-10-01']))->assertOk()->json();
        $this->assertSame([['W 1 AA', 5, 'diisi'], ['W 2 AA', 0, 'otomatis']], array_map(fn ($r) => [$r[2], $r[3], $r[11]], $d['rows']));
        $this->assertNull($d['rows'][1][10]); // baris otomatis tanpa waktu simpan
        $this->assertSame(1, $d['terisi']);
    }

    public function test_tanpa_tanggal_mulai_tidak_mengisi_apa_pun(): void
    {
        DB::table('pengaturan')->where('kunci', 'isi_otomatis_sejak')->update(['nilai' => null]);
        $this->assertSame(0, IsiOtomatis::pastikan(true));
        config(['dms.isi_otomatis_sejak' => '2026-10-05']);
        $this->assertSame(3, IsiOtomatis::pastikan(true));
    }
}
