<?php

namespace Tests\Feature;

use App\Models\Bidang;

/**
 * Uji menyeluruh: semua halaman & endpoint, semua role, parameter normal maupun tidak wajar.
 * Tidak boleh ada satu pun yang menghasilkan error server (5xx).
 */
class SmokeTest extends DmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $a = $this->user('admin');
        $this->simpan($a, $this->anglu, '2026-02-27', [['kendaraan_id' => $this->k1->id, 'jalan' => 10, 'rusak' => 2]])->assertOk();
        $this->simpan($a, $this->anglu, '2026-10-01', [['kendaraan_id' => $this->k1->id, 'jalan' => 5, 'perbaikan' => 3], ['kendaraan_id' => $this->k2->id, 'pm' => 4]])->assertOk();
        $this->simpan($a, $this->wil2, '2026-10-01', [['kendaraan_id' => $this->k3->id, 'jalan' => 20]])->assertOk();
        // satu kendaraan nonaktif agar jalur nonaktif ikut teruji
        $this->k2->update(['is_active' => false, 'nonaktif_sejak' => '2026-10-05']);
    }

    private function noServerError($res, string $ctx): void
    {
        $this->assertLessThan(500, $res->getStatusCode(), "5xx pada {$ctx}: ".substr((string) $res->getContent(), 0, 300));
    }

    public function test_semua_halaman_untuk_semua_role(): void
    {
        $bidang = Bidang::ordered();
        foreach (['admin', 'bidang', 'pemeliharaan', 'manajemen'] as $role) {
            $u = $this->user($role);
            $urls = [route('beranda'), route('profil'), route('dashboard.utilisasi'), route('dashboard.availability'),
                route('dashboard.maintenance'), route('dashboard.laporan'), route('dashboard.laporan', ['tab' => 'detail']),
                route('master.kendaraan'), route('akun.index'), '/dashboard/detail-report', '/dashboard/general-report'];
            foreach ($bidang as $b) {
                $urls[] = route('dashboard.summary', $b);
                $urls[] = route('input.show', $b);
            }
            foreach ($urls as $url) {
                $res = $this->actingAs($u)->get($url);
                $this->noServerError($res, "{$role} {$url}");
                if ($res->getStatusCode() === 200) {
                    $res->assertSee('DMS v2');
                }
            }
            $this->actingAs($u)->get(route('dashboard.summary', 'tidak-ada'))->assertNotFound();
            $this->actingAs($u)->get(route('input.show', 'tidak-ada'))->assertNotFound();
        }
    }

    public function test_endpoint_dashboard_dengan_parameter_aneh(): void
    {
        $u = $this->user('manajemen');
        $endpoints = [route('beranda.data'), route('api.dashboard.utilisasi'), route('api.dashboard.availability'),
            route('api.dashboard.maintenance'), route('api.dashboard.report'), route('api.dashboard.general'),
            route('api.dashboard.summary', $this->anglu), route('version')];
        $params = [
            [], ['tahun' => ''], ['tahun' => '', 'bulan' => ''], ['tahun' => '', 'bulan' => '10'], ['tahun' => '', 'bulan' => '', 'hari' => '1'],
            ['tahun' => '2026', 'bulan' => '2', 'hari' => '31'], ['tahun' => '2026', 'bulan' => '', 'hari' => '15'],
            ['tahun' => '1999', 'bulan' => '13', 'hari' => '0'], ['tahun' => 'abc', 'bulan' => 'x', 'hari' => 'y'],
            ['tahun' => '2026', 'bulan' => '10', 'bidang' => 'tidak-ada', 'jenis' => "X'<>\""],
            ['tahun' => '2026', 'bulan' => '10', 'bidang' => 'angkutan-luar', 'jenis' => 'DUMP TRUK B3', 'cari' => '%_'],
            ['tahun' => ['a'], 'bulan' => ['b'], 'bidang' => ['c'], 'jenis' => ['d']],
            ['tanggal_cek' => 'bukan-tanggal'],
        ];
        foreach ($endpoints as $e) {
            foreach ($params as $p) {
                $res = $this->actingAs($u)->getJson($e.'?'.http_build_query($p));
                $this->noServerError($res, $e.' '.json_encode($p));
            }
        }
        // Tanggal tidak valid (31 Feb) -> kosong, bukan error
        $this->actingAs($u)->getJson(route('api.dashboard.availability').'?tahun=2026&bulan=2&hari=31')->assertOk()->assertJsonPath('total.total_jam', 0);
    }

    public function test_halaman_admin_dengan_filter_dan_urutan_aneh(): void
    {
        $a = $this->user('admin');
        $kombinasi = [
            [], ['q' => 'W 1', 'bidang' => 'angkutan-luar', 'jenis' => 'DUMP TRUK B3', 'status' => 'aktif', 'riwayat' => 'ada', 'per' => '50'],
            ['status' => 'nonaktif', 'riwayat' => 'belum', 'sort' => 'terakhir', 'dir' => 'desc'], ['sort' => 'riwayat'], ['sort' => 'mulai', 'dir' => 'desc'],
            ['sort' => 'DROP TABLE', 'dir' => 'sideways', 'per' => '9999', 'page' => '-1'], ['q' => "%'\"<script>"], ['bidang' => ['x'], 'q' => ['y']],
        ];
        foreach ($kombinasi as $p) {
            $this->noServerError($this->actingAs($a)->get(route('master.kendaraan', $p)), 'kendaraan '.json_encode($p));
        }
        foreach ([[], ['role' => 'admin', 'status' => 'online'], ['status' => 'offline', 'bidang' => 'angkutan-luar'], ['role' => 'x', 'status' => 'y', 'sort' => 'password', 'dir' => 'z'], ['q' => "%'"]] as $p) {
            $this->noServerError($this->actingAs($a)->get(route('akun.index', $p)), 'akun '.json_encode($p));
        }
    }

    public function test_input_dengan_data_rusak_ditolak_tanpa_error_server(): void
    {
        $a = $this->user('admin');
        foreach (['', 'kemarin', '2026-13-01', '2026-02-30', '12/11/2026', now()->addDay()->toDateString()] as $tgl) {
            $res = $this->actingAs($a)->getJson(route('input.data', $this->anglu).'?tanggal='.urlencode($tgl));
            $this->noServerError($res, "data {$tgl}");
            $res->assertStatus(422);
        }
        $payloads = [
            [], ['tanggal' => '2026-10-02'], ['tanggal' => '2026-10-02', 'rows' => 'bukan-array'], ['tanggal' => '2026-10-02', 'rows' => []],
            ['tanggal' => '2026-10-02', 'rows' => [['kendaraan_id' => 'abc']]],
            ['tanggal' => '2026-10-02', 'rows' => [['kendaraan_id' => $this->k1->id, 'jalan' => '24abc']]],
            ['tanggal' => '2026-10-02', 'rows' => [['kendaraan_id' => $this->k1->id, 'jalan' => -5]]],
            ['tanggal' => '2026-10-02', 'rows' => [['kendaraan_id' => $this->k1->id, 'jalan' => 1.5]]],
            ['tanggal' => '2026-10-02', 'rows' => [['kendaraan_id' => $this->k1->id, 'jalan' => 20, 'rusak' => 10]]],
            ['tanggal' => '2026-10-02', 'rows' => [['kendaraan_id' => 999999, 'jalan' => 1]]],
            ['tanggal' => '2026-10-02', 'rows' => [['kendaraan_id' => $this->k1->id, 'jalan' => 1, 'asal' => 'x']]],
            ['tanggal' => '2026-10-02', 'rows' => [['kendaraan_id' => $this->k1->id, 'jalan' => 1, 'asal' => ['jalan' => 'abc']]]],
        ];
        foreach ($payloads as $p) {
            $res = $this->actingAs($a)->postJson(route('input.simpan', $this->anglu), $p);
            $this->noServerError($res, 'simpan '.json_encode($p));
            $this->assertContains($res->getStatusCode(), [200, 409, 422], 'simpan '.json_encode($p));
        }
    }

    public function test_aksi_master_dengan_input_tidak_wajar(): void
    {
        $a = $this->user('admin');
        $this->k1->update(['aktif_sejak' => '2026-01-01']);
        $cek = fn ($res, $ctx) => $this->noServerError($res, $ctx);
        $cek($this->actingAs($a)->post(route('master.kendaraan.store'), ['jenis_kendaraan_id' => 'x', 'nomor_kendaraan' => '', 'aktif_sejak' => 'kemarin']), 'store');
        $cek($this->actingAs($a)->put(route('master.kendaraan.update', $this->k1), ['jenis_kendaraan_id' => $this->k1->jenis_kendaraan_id, 'nomor_kendaraan' => 'W 1 AA', 'aktif_sejak' => '2027-99-99']), 'update');
        $cek($this->actingAs($a)->patch(route('master.kendaraan.toggle', $this->k1), ['aksi' => 'nonaktif', 'nonaktif_sejak' => 'x']), 'toggle');
        $this->actingAs($a)->patch(route('master.kendaraan.toggle', $this->k1), ['aksi' => 'nonaktif', 'nonaktif_sejak' => '2000-01-01'])
            ->assertSessionHas('error'); // sebelum tanggal mulai beroperasi -> ditolak dengan pesan
        $cek($this->actingAs($a)->post(route('master.jenis.store'), ['bidang_id' => 999, 'nama' => '']), 'jenis');
        $cek($this->actingAs($a)->put(route('master.bidang.update', $this->anglu->id), ['nama_tampil' => '', 'urutan' => 'x']), 'bidang');
        $cek($this->actingAs($a)->post(route('akun.store'), ['username' => 'bukan-email', 'role' => 'raja']), 'akun store');
        $cek($this->actingAs($a)->put(route('profil.password'), ['password_lama' => 'salah', 'password' => 'a', 'password_confirmation' => 'b']), 'password');
        $this->actingAs($a)->get('/master/kendaraan/999999')->assertStatus(405);
        $this->actingAs($a)->patch(route('master.kendaraan.toggle', 999999), ['aksi' => 'aktif'])->assertNotFound();
        $this->assertTrue($this->k1->fresh()->is_active);
    }

    public function test_input_berbentuk_array_tidak_merusak_halaman(): void
    {
        $a = $this->user('admin');
        $cek = fn ($res, $ctx) => $this->noServerError($res, $ctx);
        $cek($this->post(route('login.attempt'), ['username' => ['x'], 'password' => ['y']]), 'login array');
        $cek($this->get(route('login')), 'login setelah array');
        $cek($this->actingAs($a)->from(route('akun.index'))->post(route('akun.store'), ['name' => ['x'], 'username' => ['y'], 'role' => ['z']]), 'akun array');
        $cek($this->actingAs($a)->get(route('akun.index')), 'akun setelah array');
        $cek($this->actingAs($a)->from(route('master.kendaraan'))->post(route('master.kendaraan.store'), ['nomor_kendaraan' => ['x'], 'jenis_kendaraan_id' => ['1'], 'aktif_sejak' => ['z']]), 'kendaraan array');
        $cek($this->actingAs($a)->get(route('master.kendaraan')), 'kendaraan setelah array');
        $cek($this->actingAs($a)->post(route('master.jenis.store'), ['bidang_id' => ['1'], 'nama' => ['x']]), 'jenis array');
        $cek($this->actingAs($a)->patch(route('master.kendaraan.toggle', $this->k1), ['aksi' => ['aktif'], 'nonaktif_sejak' => ['x']]), 'toggle array');
        $cek($this->actingAs($a)->get(route('master.kendaraan', ['q' => ['a'], 'jenis' => ['b'], 'status' => ['c'], 'sort' => ['d'], 'per' => ['e']])), 'kendaraan filter array');
        $cek($this->actingAs($a)->get(route('akun.index', ['q' => ['a'], 'role' => ['b'], 'sort' => ['c']])), 'akun filter array');
    }

    public function test_url_lama_dialihkan_semua(): void
    {
        foreach (['sheetAnglu', 'sheetAngdal', 'sheetAlberWil1pg', 'sheetAlberWil2pg', 'sheetAlberWil2fi', 'dashboardAnglu', 'dashboardAngdal',
            'dashboardAlberWil1', 'dashboardAlberWil2', 'dashboardAlberWil3', 'angkutanLuar', 'utilisasi', 'availability', 'maintenance',
            'reports', 'GeneralReports', 'masterKendaraan', 'account', 'login'] as $lama) {
            $this->get('/'.$lama.'.php')->assertRedirect();
        }
    }
}
