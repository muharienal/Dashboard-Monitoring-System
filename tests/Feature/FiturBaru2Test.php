<?php

namespace Tests\Feature;

use App\Models\JenisKendaraan;
use App\Models\Kendaraan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Perbandingan KPI, tanda kelengkapan di tren, dan riwayat data master. */
class FiturBaru2Test extends DmsTestCase
{
    private function baris(Kendaraan $k, string $tgl, int $jalan = 8, int $perbaikan = 0): void
    {
        $standby = 24 - $jalan - $perbaikan;
        DB::table('sheet_data')->insert([
            'tanggal' => $tgl, 'kendaraan_id' => $k->id, 'bidang_id' => $this->anglu->id, 'jenis_kendaraan_id' => $k->jenis_kendaraan_id,
            'jalan' => $jalan, 'standby' => $standby, 'rusak' => 0, 'perbaikan' => $perbaikan, 'pm' => 0, 'total_jam_kerja' => 24,
            'availability_rate' => round(($jalan + $standby) / 24 * 100, 2), 'utilitas_jalan' => $jalan > 4 && ! $perbaikan ? 1 : 0,
            'utilitas_standby' => $jalan > 4 && ! $perbaikan ? 0 : 1, 'utilitas_rusak' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ------------------------------------------------------------ Perbandingan KPI
    public function test_bulan_berjalan_dibanding_tanggal_yang_sama_bulan_lalu(): void
    {
        $kini = CarbonImmutable::today();
        $lalu = $kini->subMonthNoOverflow()->startOfMonth();
        $this->baris($this->k1, $kini->startOfMonth()->toDateString(), 8, 0);
        $this->baris($this->k1, $lalu->toDateString(), 0, 12);               // selalu ikut (tanggal 1)
        $this->baris($this->k2, $lalu->endOfMonth()->toDateString(), 0, 6);  // ikut hanya bila hari ini >= tanggal itu

        $d = $this->actingAs($this->user('manajemen'))->getJson(route('beranda.data', ['tahun' => $kini->year, 'bulan' => $kini->month]))->assertOk()->json();
        $p = $d['pembanding'];
        $this->assertTrue($p['sebagian']);
        $this->assertStringStartsWith('1–', $p['label']);
        $ikutAkhir = $kini->day >= $lalu->daysInMonth;
        $this->assertSame($ikutAkhir ? 2 : 1, $p['availability']['unit_hari']);
        $this->assertSame($ikutAkhir ? 18 : 12, $p['availability']['jam']['PERBAIKAN']);
        $this->assertSame(0, $d['availability']['jam']['PERBAIKAN']);
    }

    public function test_bulan_lampau_dibanding_bulan_penuh_dan_tahun_dibanding_tahun_lalu(): void
    {
        $m = $this->actingAs($this->user('manajemen'))->getJson(route('beranda.data', ['tahun' => 2026, 'bulan' => 1]))->json('pembanding');
        $this->assertSame(['Desember 2025', false], [$m['label'], $m['sebagian']]);
        $y = $this->actingAs($this->user('manajemen'))->getJson(route('beranda.data', ['tahun' => 2024, 'bulan' => '']))->json('pembanding');
        $this->assertSame(['2023', false], [$y['label'], $y['sebagian']]);
        $this->assertNull($this->actingAs($this->user('manajemen'))->getJson(route('beranda.data', ['tahun' => '', 'bulan' => 3]))->json('pembanding'));
    }

    // -------------------------------------------------- Tanda kelengkapan di grafik tren
    public function test_tren_beranda_menandai_tanggal_belum_lengkap(): void
    {
        $tgl = CarbonImmutable::create(2026, 10, 5);
        $this->baris($this->k1, $tgl->toDateString());
        $this->baris($this->k2, $tgl->toDateString());
        $this->baris($this->k1, $tgl->addDay()->toDateString());   // k2 belum diisi tanggal 6

        $tren = collect($this->actingAs($this->user('manajemen'))->getJson(route('beranda.data', ['tahun' => 2026, 'bulan' => 10]))->json('tren'))->keyBy('periode');
        $this->assertCount(31, $tren);
        // Semua bidang: 3 kendaraan uji aktif tiap hari (k1, k2 di Angkutan Luar, k3 di Alber Wil 2).
        $this->assertSame(['unit' => 3, 'terisi' => 2, 'lengkap' => false], array_intersect_key($tren['2026-10-05'], array_flip(['unit', 'terisi', 'lengkap'])));
        $this->assertSame(1, $tren['2026-10-06']['terisi']);
        $this->assertSame(0, $tren['2026-10-07']['unit_hari']);

        // Dashboard Bidang Angkutan Luar: tanggal 5 lengkap (2/2), tanggal 6 belum (1/2).
        $b = collect($this->actingAs($this->user('manajemen'))->getJson(route('api.dashboard.summary', $this->anglu).'?tahun=2026&bulan=10')->json('tren'))->keyBy('periode');
        $this->assertTrue($b['2026-10-05']['lengkap']);
        $this->assertFalse($b['2026-10-06']['lengkap']);

        // Kendaraan yang mulai beroperasi 20 Oktober tidak dihitung sebelum tanggal itu.
        $this->k2->update(['aktif_sejak' => '2026-10-20']);
        $b = collect($this->actingAs($this->user('manajemen'))->getJson(route('api.dashboard.summary', $this->anglu).'?tahun=2026&bulan=10')->json('tren'))->keyBy('periode');
        $this->assertSame(1, $b['2026-10-10']['unit']);
        $this->assertSame(2, $b['2026-10-20']['unit']);
    }

    public function test_tren_per_tahun_per_bulan_dan_tidak_melewati_hari_ini(): void
    {
        $t = now()->year;
        $tren = $this->actingAs($this->user('manajemen'))->getJson(route('api.dashboard.summary', $this->anglu)."?tahun={$t}&bulan=")->assertOk()->json();
        $this->assertSame('bulan', $tren['tren_mode']);
        $this->assertCount(now()->month, $tren['tren']);
        $this->assertSame(now()->format('Y-m'), end($tren['tren'])['periode']);
    }

    // ------------------------------------------------------- Riwayat data master
    public function test_riwayat_master_kendaraan_jenis_bidang(): void
    {
        $admin = $this->user('admin');
        $dtLuar = $this->k1->jenis_kendaraan_id;
        $jenisDalam = JenisKendaraan::where('bidang_id', '!=', $this->anglu->id)->first();

        $this->actingAs($admin)->post(route('master.kendaraan.store'), ['jenis_kendaraan_id' => $dtLuar, 'nomor_kendaraan' => 'w 77 zz', 'aktif_sejak' => '2026-11-01'])->assertSessionHas('success');
        $k = Kendaraan::where('nomor_kendaraan', 'W 77 ZZ')->firstOrFail();
        $this->actingAs($admin)->put(route('master.kendaraan.update', $k), ['jenis_kendaraan_id' => $jenisDalam->id, 'nomor_kendaraan' => 'W 77 ZZ', 'aktif_sejak' => '2026-11-01'])->assertSessionHas('success');
        $this->actingAs($admin)->put(route('master.kendaraan.update', $k), ['jenis_kendaraan_id' => $jenisDalam->id, 'nomor_kendaraan' => 'W 77 ZZ', 'aktif_sejak' => '2026-11-01']); // tanpa perubahan
        $this->actingAs($admin)->patch(route('master.kendaraan.toggle', $k), ['aksi' => 'nonaktif', 'nonaktif_sejak' => now()->toDateString(), 'keterangan' => 'Dijual']);
        $this->actingAs($admin)->patch(route('master.kendaraan.toggle', $k), ['aksi' => 'aktif']);
        $this->actingAs($admin)->delete(route('master.kendaraan.destroy', $k))->assertSessionHas('success');
        $this->actingAs($admin)->post(route('master.jenis.store'), ['bidang_id' => $this->anglu->id, 'nama' => 'jenis uji']);
        $j = JenisKendaraan::where('nama', 'JENIS UJI')->firstOrFail();
        $this->actingAs($admin)->put(route('master.jenis.update', $j), ['nama' => 'JENIS UJI 2']);
        $this->actingAs($admin)->put(route('master.bidang.update', $this->anglu->id), ['nama_tampil' => 'Angkutan Luar Baru', 'urutan' => 1, 'input_semua_kolom' => 1]);

        $r = DB::table('riwayat_master')->orderBy('id')->get();
        $this->assertSame(['tambah', 'ubah', 'nonaktif', 'aktif', 'hapus', 'tambah', 'ubah', 'ubah'], $r->pluck('aksi')->all());
        $this->assertSame(['kendaraan', 'kendaraan', 'kendaraan', 'kendaraan', 'kendaraan', 'jenis', 'jenis', 'bidang'], $r->pluck('objek')->all());
        $pindah = collect(json_decode($r[1]->perubahan, true))->keyBy('kolom');
        $this->assertSame($this->anglu->nama_tampil, $pindah['bidang']['dari']);
        $this->assertSame($jenisDalam->bidang->nama_tampil, $pindah['bidang']['ke']);
        $non = collect(json_decode($r[2]->perubahan, true))->keyBy('kolom');
        $this->assertSame(['Aktif', 'Nonaktif'], [$non['status']['dari'], $non['status']['ke']]);
        $this->assertSame('Dijual', $non['keterangan']['ke']);
        $this->assertSame($admin->name, $r[0]->user_nama);
        $bid = collect(json_decode($r[7]->perubahan, true))->keyBy('kolom');
        $this->assertSame(['Tidak', 'Ya'], [$bid['semua_kolom']['dari'], $bid['semua_kolom']['ke']]);

        $this->actingAs($admin)->get(route('riwayat.index', ['tab' => 'master']))->assertOk()
            ->assertSee('Data master')->assertSee('W 77 ZZ')->assertSee('Dinonaktifkan')->assertSee('Angkutan Luar Baru');
        $this->actingAs($admin)->get(route('riwayat.index', ['tab' => 'master', 'objek' => 'jenis']))->assertOk()->assertDontSee('W 77 ZZ');
        $x = $this->actingAs($admin)->getJson(route('riwayat.index', ['tab' => 'master', 'format' => 'xlsx']))->json();
        $this->assertSame(8, $x['jumlah']);
    }

    public function test_riwayat_master_akun_tanpa_menyimpan_password(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->post(route('akun.store'), ['name' => 'Sari', 'username' => 'sari', 'role' => 'bidang', 'bidang_id' => $this->anglu->id,
            'is_active' => 1, 'password' => 'Rahasia#123', 'password_confirmation' => 'Rahasia#123'])->assertSessionHas('success');
        $sari = User::where('username', 'sari')->firstOrFail();
        $this->actingAs($admin)->put(route('akun.update', $sari), ['name' => 'Sari', 'username' => 'sari', 'role' => 'pemeliharaan',
            'is_active' => 1, 'password' => 'Baru#12345', 'password_confirmation' => 'Baru#12345'])->assertSessionHas('success');
        $this->actingAs($sari->fresh())->put(route('profil.password'), ['password_lama' => 'Baru#12345', 'password' => 'Sendiri#123', 'password_confirmation' => 'Sendiri#123'])->assertSessionHas('success');
        $this->actingAs($admin)->delete(route('akun.destroy', $sari))->assertSessionHas('success');

        $r = DB::table('riwayat_master')->where('objek', 'akun')->orderBy('id')->get();
        $this->assertSame(['tambah', 'ubah', 'password', 'hapus'], $r->pluck('aksi')->all());
        $ubah = collect(json_decode($r[1]->perubahan, true))->keyBy('kolom');
        $this->assertSame(['Bidang', 'Pemeliharaan'], [$ubah['role']['dari'], $ubah['role']['ke']]);
        $this->assertSame('Password diganti oleh admin', $r[1]->catatan);
        $this->assertSame('Sari', $r[2]->user_nama);
        $semua = DB::table('riwayat_master')->get()->map(fn ($x) => json_encode($x))->join(' ');
        $this->assertStringNotContainsString('Rahasia#123', $semua);
        $this->assertStringNotContainsString('Baru#12345', $semua);
        $this->assertStringNotContainsString('Sendiri#123', $semua);
        // Setelah akun dihapus, nama pengubah tetap terbaca.
        $this->actingAs($admin)->get(route('riwayat.index', ['tab' => 'master', 'objek' => 'akun']))->assertOk()->assertSee('akun dihapus');
    }

    public function test_riwayat_master_hanya_admin_dan_filter_aneh_aman(): void
    {
        $this->actingAs($this->user('manajemen'))->get(route('riwayat.index', ['tab' => 'master']))->assertForbidden();
        $this->actingAs($this->user('admin'))->get(route('riwayat.index', ['tab' => 'master', 'objek' => 'x', 'aksi' => ['a'], 'oleh' => 'z', 'dari' => '2026-13-40', 'q' => ['q']]))->assertOk();
    }
}
