<?php

namespace Tests\Feature;

use App\Models\JenisKendaraan;
use App\Models\Kendaraan;
use Illuminate\Support\Facades\DB;

/** Pemeriksaan akhir: matriks hak akses semua halaman/endpoint, parameter aneh, dan XSS. */
class FinalisasiTest extends DmsTestCase
{
    /** [route, parameter, [admin, bidang, pemeliharaan, manajemen]] — status HTTP yang diharapkan. */
    private function matriks(): array
    {
        $k = $this->k1->id;

        return [
            ['beranda', [], [200, 200, 200, 200]],
            ['beranda.data', [], [200, 200, 200, 200]],
            ['input.show', ['bidang' => 'angkutan-luar'], [200, 200, 200, 403]],
            ['input.show', ['bidang' => 'alber-wil-2'], [200, 403, 200, 403]],
            ['input.data', ['bidang' => 'alber-wil-2', 'tanggal' => now()->toDateString()], [200, 403, 200, 403]],
            ['dashboard.summary', ['bidang' => 'angkutan-luar'], [200, 200, 200, 200]],
            ['dashboard.utilisasi', [], [200, 200, 200, 200]],
            ['dashboard.availability', [], [200, 200, 200, 200]],
            ['dashboard.maintenance', [], [200, 200, 200, 200]],
            ['dashboard.laporan', [], [200, 200, 200, 200]],
            ['dashboard.perbandingan', [], [200, 200, 200, 200]],
            ['kendaraan.profil', ['kendaraan' => $k], [200, 200, 200, 200]],
            ['kendaraan.cari', ['q' => 'w'], [200, 200, 200, 200]],
            ['laporan.bulanan', [], [200, 200, 200, 200]],
            ['riwayat.index', [], [200, 200, 200, 200]],
            ['riwayat.index', ['tab' => 'master'], [200, 403, 403, 403]],
            ['riwayat.index', ['tab' => 'login'], [200, 403, 403, 403]],
            ['profil', [], [200, 200, 200, 200]],
            ['master.kendaraan', [], [200, 403, 403, 403]],
            ['akun.index', [], [200, 403, 403, 403]],
            ['tv', [], [200, 403, 403, 403]],
        ];
    }

    public function test_matriks_hak_akses_semua_halaman(): void
    {
        $users = [$this->user('admin'), $this->user('bidang', $this->anglu->id), $this->user('pemeliharaan'), $this->user('manajemen')];
        foreach ($this->matriks() as [$route, $param, $harap]) {
            foreach ($users as $i => $u) {
                $kode = $this->actingAs($u)->get(route($route, $param))->getStatusCode();
                $this->assertSame($harap[$i], $kode, "{$route} ".json_encode($param)." sebagai {$u->role}");
            }
            // Tamu selalu diarahkan ke login (atau 401 untuk permintaan data)
            $this->app['auth']->forgetGuards();
            $this->flushSession();
            $tamu = $this->get(route($route, $param))->getStatusCode();
            $this->assertContains($tamu, [302, 401], "{$route} sebagai tamu");
        }
    }

    public function test_aksi_tulis_ditolak_untuk_role_yang_tidak_berhak(): void
    {
        $bidang = $this->user('bidang', $this->anglu->id);
        $manajemen = $this->user('manajemen');
        foreach ([$bidang, $manajemen] as $u) {
            $this->actingAs($u)->post(route('master.kendaraan.store'), ['nomor_kendaraan' => 'X 1'])->assertForbidden();
            $this->actingAs($u)->post(route('akun.store'), ['name' => 'x'])->assertForbidden();
            $this->actingAs($u)->postJson(route('tv.ingat'))->assertForbidden();
        }
        $this->actingAs($manajemen)->postJson(route('input.simpan', $this->anglu), ['tanggal' => now()->toDateString(), 'rows' => []])->assertForbidden();
        $this->actingAs($bidang)->postJson(route('input.simpan', $this->wil2), ['tanggal' => now()->toDateString(), 'rows' => []])->assertForbidden();
        // Tanpa token CSRF di luar tes, Laravel menolak; di sini cukup pastikan middleware web aktif pada POST simpan.
        $this->assertContains('web', app('router')->getRoutes()->getByName('input.simpan')->gatherMiddleware());
    }

    public function test_parameter_aneh_tidak_membuat_error_server(): void
    {
        $u = $this->user('admin');
        $aneh = ['tahun' => ['x'], 'bulan' => '13', 'hari' => '-1', 'bidang' => '../../etc', 'jenis' => str_repeat('a', 500), 'urut' => '<b>', 'akhir' => '9999-99-99', 'q' => ['a' => 'b'], 'tab' => ['x'], 'tanggal' => '2026-02-31'];
        foreach (['beranda', 'beranda.data', 'dashboard.utilisasi', 'dashboard.availability', 'dashboard.maintenance', 'dashboard.laporan',
            'dashboard.perbandingan', 'laporan.bulanan', 'riwayat.index', 'akun.index', 'master.kendaraan', 'kendaraan.cari', 'tv'] as $route) {
            $kode = $this->actingAs($u)->get(route($route, $aneh))->getStatusCode();
            $this->assertLessThan(500, $kode, $route);
        }
        foreach (['api.dashboard.availability', 'api.dashboard.utilisasi', 'api.dashboard.maintenance', 'api.dashboard.report', 'api.dashboard.general'] as $route) {
            $this->assertLessThan(500, $this->actingAs($u)->getJson(route($route, $aneh))->getStatusCode(), $route);
        }
        $this->assertLessThan(500, $this->actingAs($u)->get(route('kendaraan.profil', ['kendaraan' => $this->k1->id] + $aneh))->getStatusCode());
        $this->assertLessThan(500, $this->actingAs($u)->getJson(route('api.dashboard.summary', ['bidang' => 'angkutan-luar'] + $aneh))->getStatusCode());
        $this->assertLessThan(500, $this->actingAs($u)->getJson(route('input.data', ['bidang' => 'angkutan-luar'] + $aneh))->getStatusCode());
        // Bulan depan & tahun "Semua"
        foreach ([['tahun' => now()->addYear()->year, 'bulan' => 1], ['tahun' => '', 'bulan' => ''], ['tahun' => 2024, 'bulan' => '']] as $p) {
            $this->actingAs($u)->getJson(route('beranda.data', $p))->assertOk();
            $this->actingAs($u)->get(route('dashboard.perbandingan', $p))->assertOk();
            $this->actingAs($u)->get(route('laporan.bulanan', $p))->assertOk();
        }
    }

    public function test_data_berisi_html_ditampilkan_sebagai_teks(): void
    {
        $jahat = '<img src=x onerror=alert(1)>';
        $jenis = JenisKendaraan::create(['bidang_id' => $this->anglu->id, 'nama' => 'JENIS '.$jahat]);
        $k = Kendaraan::create(['jenis_kendaraan_id' => $jenis->id, 'nomor_kendaraan' => 'N '.$jahat, 'keterangan' => $jahat]);
        DB::table('sheet_data')->insert(['tanggal' => now()->toDateString(), 'kendaraan_id' => $k->id, 'bidang_id' => $this->anglu->id, 'jenis_kendaraan_id' => $jenis->id,
            'jalan' => 0, 'standby' => 10, 'rusak' => 0, 'perbaikan' => 14, 'pm' => 0, 'total_jam_kerja' => 24, 'availability_rate' => 41.67,
            'utilitas_jalan' => 0, 'utilitas_standby' => 0, 'utilitas_rusak' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $admin = $this->user('admin');
        $admin->forceFill(['name' => 'Admin '.$jahat])->save();
        foreach ([route('kendaraan.profil', $k->id), route('master.kendaraan'), route('akun.index'), route('laporan.bulanan'), route('beranda'),
            route('input.show', 'angkutan-luar'), route('dashboard.perbandingan')] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString($jahat, $html, $url);
        }
        // Data JSON dikirim apa adanya; tampilan sisi browser memakai DMS.esc (diperiksa di uji browser).
        $this->actingAs($admin)->getJson(route('api.dashboard.maintenance'))->assertOk();
    }
}
