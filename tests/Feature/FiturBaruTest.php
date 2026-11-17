<?php

namespace Tests\Feature;

use App\Models\PermintaanResetPassword;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/** Pengingat input, riwayat perubahan, laporan bulanan, dan lupa password. */
class FiturBaruTest extends DmsTestCase
{
    private function kl(array $data, int $hari, string $kode): array
    {
        return collect($data['kelengkapan'][$hari]['bidang'])->firstWhere('kode', $kode);
    }

    // ---------------------------------------------------------------- Pengingat input
    public function test_beranda_menghitung_unit_yang_belum_diisi_kemarin_dan_hari_ini(): void
    {
        $admin = $this->user('admin');
        $this->simpan($admin, $this->anglu, now()->toDateString(), [['kendaraan_id' => $this->k1->id, 'jalan' => 8]])->assertOk();

        $d = $this->actingAs($admin)->getJson(route('beranda.data'))->assertOk()->json();
        $this->assertSame([now()->subDay()->toDateString(), now()->toDateString()], array_column($d['kelengkapan'], 'tanggal'));
        $this->assertSame(['unit' => 2, 'terisi' => 1, 'belum' => 1, 'bisa_input' => true],
            array_intersect_key($this->kl($d, 1, 'angkutan-luar'), array_flip(['unit', 'terisi', 'belum', 'bisa_input'])));
        $this->assertSame(2, $this->kl($d, 0, 'angkutan-luar')['belum']);
        $this->assertSame(1, $this->kl($d, 1, 'alber-wil-2')['belum']);

        // Manajemen hanya melihat, tidak diberi tombol isi.
        $m = $this->actingAs($this->user('manajemen'))->getJson(route('beranda.data'))->json();
        $this->assertFalse($this->kl($m, 1, 'angkutan-luar')['bisa_input']);
    }

    public function test_kendaraan_nonaktif_tidak_dihitung_belum_diisi(): void
    {
        $this->k2->update(['nonaktif_sejak' => now()->toDateString(), 'is_active' => false]);
        $d = $this->actingAs($this->user('admin'))->getJson(route('beranda.data'))->json();
        $this->assertSame(1, $this->kl($d, 1, 'angkutan-luar')['unit'], 'hari ini k2 sudah nonaktif');
        $this->assertSame(2, $this->kl($d, 0, 'angkutan-luar')['unit'], 'kemarin k2 masih beroperasi');
    }

    public function test_kelengkapan_bulan_menghitung_unit_hari(): void
    {
        $tgl = now()->subMonthNoOverflow()->startOfMonth();
        $hari = $tgl->daysInMonth;
        for ($i = 0; $i < $hari; $i++) {
            DB::table('sheet_data')->insert(['tanggal' => $tgl->copy()->addDays($i)->toDateString(), 'kendaraan_id' => $this->k1->id,
                'bidang_id' => $this->anglu->id, 'jenis_kendaraan_id' => $this->k1->jenis_kendaraan_id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $l = app(DashboardService::class)->kelengkapanBulan($tgl->year, $tgl->month);
        $this->assertSame(['seharusnya' => $hari * 2, 'terisi' => $hari, 'persen' => 50.0], $l['angkutan-luar']);
        $this->assertSame(0, $l['alber-wil-2']['terisi']);
    }

    // --------------------------------------------------------------- Riwayat perubahan
    public function test_riwayat_mencatat_data_baru_dan_perubahan(): void
    {
        $admin = $this->user('admin');
        $tgl = now()->toDateString();
        $this->simpan($admin, $this->anglu, $tgl, [['kendaraan_id' => $this->k1->id, 'jalan' => 8]])->assertOk();
        $petugas = $this->user('bidang', $this->anglu->id);
        $this->simpan($petugas, $this->anglu, $tgl, [['kendaraan_id' => $this->k1->id, 'jalan' => 10, 'rusak' => 2, 'asal' => ['jalan' => 8, 'rusak' => 0]]])->assertOk();
        // Simpan ulang tanpa perubahan tidak menambah riwayat.
        $this->simpan($petugas, $this->anglu, $tgl, [['kendaraan_id' => $this->k1->id, 'jalan' => 10, 'rusak' => 2]])->assertOk();

        $r = DB::table('riwayat_input')->orderBy('id')->get();
        $this->assertCount(2, $r);
        $this->assertSame('buat', $r[0]->aksi);
        $this->assertNull($r[0]->sebelum);
        $this->assertSame(['jalan' => 8, 'standby' => 16, 'rusak' => 0, 'perbaikan' => 0, 'pm' => 0], json_decode($r[0]->sesudah, true));
        $this->assertSame('ubah', $r[1]->aksi);
        $this->assertSame(8, json_decode($r[1]->sebelum, true)['jalan']);
        $this->assertSame(['jalan' => 10, 'standby' => 12, 'rusak' => 2, 'perbaikan' => 0, 'pm' => 0], json_decode($r[1]->sesudah, true));
        $this->assertSame($petugas->name, $r[1]->user_nama);
        $this->assertSame('W 1 AA', $r[1]->nomor_kendaraan);

        $this->actingAs($admin)->get(route('riwayat.index'))->assertOk()
            ->assertSee('Riwayat')->assertSee('W 1 AA')->assertSee('text-secondary">Data baru</span>', false)->assertSeeInOrder(['Jalan', '8', '10'])
            ->assertSee('Standby');
        $this->actingAs($admin)->get(route('riwayat.index', ['aksi' => 'ubah', 'oleh' => $petugas->id]))->assertOk()->assertDontSee('text-secondary">Data baru</span>', false);
        $x = $this->actingAs($admin)->getJson(route('riwayat.index', ['format' => 'xlsx']))->assertOk()->json();
        $this->assertSame(2, $x['jumlah']);
        $this->assertStringContainsString('Jalan 8 → 10', $x['aoa'][2][6]);
    }

    public function test_riwayat_sesuai_bidang_dan_tetap_terbaca_setelah_akun_dihapus(): void
    {
        $petugas = $this->user('bidang', $this->anglu->id);
        $petugasWil2 = $this->user('bidang', $this->wil2->id);
        $this->simpan($petugas, $this->anglu, now()->toDateString(), [['kendaraan_id' => $this->k1->id, 'jalan' => 5]])->assertOk();
        $this->simpan($this->user('admin'), $this->wil2, now()->toDateString(), [['kendaraan_id' => $this->k3->id, 'jalan' => 7]])->assertOk();

        // Petugas bidang: hanya bidangnya, walau URL meminta bidang lain; tab master/login ditolak
        $this->actingAs($petugas)->get(route('riwayat.index'))->assertOk()->assertSee('W 1 AA')->assertDontSee('EXC 01')->assertSee('Menampilkan riwayat bidang');
        $this->actingAs($petugas)->get(route('riwayat.index', ['bidang' => 'alber-wil-2']))->assertOk()->assertDontSee('EXC 01');
        $this->assertSame(1, $this->actingAs($petugas)->getJson(route('riwayat.index', ['format' => 'xlsx']))->json('jumlah'));
        $this->actingAs($petugas)->get(route('riwayat.index', ['tab' => 'master']))->assertForbidden();
        $this->actingAs($petugas)->get(route('riwayat.index', ['tab' => 'login']))->assertForbidden();
        $this->actingAs($petugasWil2)->get(route('riwayat.index'))->assertOk()->assertSee('EXC 01')->assertDontSee('W 1 AA');
        // Profil kendaraan: riwayat tampil hanya untuk unit bidangnya
        $this->actingAs($petugas)->get(route('kendaraan.profil', $this->k1->id))->assertSee('Riwayat perubahan');
        $this->actingAs($petugas)->get(route('kendaraan.profil', $this->k3->id))->assertDontSee('Riwayat perubahan');

        // Pemeliharaan & manajemen: semua bidang, tanpa tab master/login
        foreach (['pemeliharaan', 'manajemen'] as $role) {
            $this->actingAs($this->user($role))->get(route('riwayat.index'))->assertOk()->assertSee('W 1 AA')->assertSee('EXC 01')->assertDontSee('aria-label="Jenis riwayat"', false);
            $this->actingAs($this->user($role))->get(route('kendaraan.profil', $this->k3->id))->assertSee('Riwayat perubahan');
        }

        $petugas->delete();
        $this->actingAs($this->user('admin'))->get(route('riwayat.index'))->assertOk()->assertSee('Bidang')->assertSee('akun dihapus');
    }

    public function test_filter_riwayat_aneh_tidak_error(): void
    {
        $this->actingAs($this->user('admin'))->get(route('riwayat.index', ['q' => ['x'], 'oleh' => 'abc', 'tanggal' => '2026-02-31', 'dari' => 'x', 'per' => 999, 'bidang' => 'tidak-ada']))->assertOk();
    }

    // ---------------------------------------------------------------- Laporan bulanan
    public function test_laporan_bulanan_bisa_dibuka_semua_role(): void
    {
        $this->simpan($this->user('admin'), $this->anglu, now()->toDateString(), [['kendaraan_id' => $this->k1->id, 'jalan' => 8, 'perbaikan' => 3]])->assertOk();
        $this->actingAs($this->user('manajemen'))->get(route('laporan.bulanan', ['tahun' => now()->year, 'bulan' => now()->month]))->assertOk()
            ->assertSee('LAPORAN BULANAN OPERASIONAL KENDARAAN')->assertSee($this->anglu->nama_tampil)->assertSee('W 1 AA')
            ->assertSee('Kelengkapan input')->assertDontSee('Mengetahui');
        // Parameter tidak valid -> bulan ini
        $this->actingAs($this->user('bidang'))->get(route('laporan.bulanan', ['tahun' => 'x', 'bulan' => 13]))->assertOk()
            ->assertSee(now()->translatedFormat('F Y'));
        // Bulan depan: belum ada data
        $depan = now()->addMonthNoOverflow();
        $this->actingAs($this->user('bidang'))->get(route('laporan.bulanan', ['tahun' => $depan->year, 'bulan' => $depan->month]))->assertOk()->assertSee('belum dimulai');
    }

    public function test_laporan_bulanan_perlu_login(): void
    {
        $this->get(route('laporan.bulanan'))->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------ Lupa password
    private function budi(): User
    {
        return User::create(['name' => 'Budi', 'username' => 'budi', 'password' => 'Lama#12345', 'role' => 'bidang', 'is_active' => true]);
    }

    public function test_lupa_password_mengirim_permintaan_tanpa_duplikat(): void
    {
        $budi = $this->budi();
        $this->get(route('login'))->assertSee('Lupa password?');
        $this->get(route('lupa-password'))->assertOk()->assertSee('Kirim permintaan');

        $this->post(route('lupa-password.kirim'), ['username' => ' Budi ', 'catatan' => 'HP 0812'])
            ->assertRedirect(route('login'))->assertSessionHas('success');
        $this->post(route('lupa-password.kirim'), ['username' => 'budi']);
        $this->assertSame(1, PermintaanResetPassword::count());
        $this->assertSame('HP 0812', PermintaanResetPassword::first()->catatan);
        $this->assertSame($budi->id, PermintaanResetPassword::first()->user_id);

        // Nama tidak dikenal / akun nonaktif: pesan sama, tidak ada data baru.
        $this->post(route('lupa-password.kirim'), ['username' => 'tidakada'])->assertRedirect(route('login'))
            ->assertSessionHas('success', \App\Http\Controllers\Auth\LupaPasswordController::PESAN);
        User::create(['name' => 'Off', 'username' => 'off', 'password' => 'x', 'role' => 'bidang', 'is_active' => false]);
        $this->post(route('lupa-password.kirim'), ['username' => 'off']);
        $this->assertSame(1, PermintaanResetPassword::count());
    }

    public function test_lupa_password_dibatasi_dan_divalidasi(): void
    {
        RateLimiter::clear('lupa-password|127.0.0.1');
        $this->from(route('lupa-password'))->post(route('lupa-password.kirim'), ['username' => ''])->assertSessionHasErrors('username');
        $this->post(route('lupa-password.kirim'), ['username' => ['x']])->assertSessionHasErrors('username');
        // Isian kosong ditolak validasi dan tidak dihitung; 5 permintaan sah per 10 menit per IP.
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('lupa-password.kirim'), ['username' => 'a'.$i]);
        }
        $this->from(route('lupa-password'))->post(route('lupa-password.kirim'), ['username' => 'lagi'])->assertSessionHas('error');
    }

    public function test_admin_mengatur_password_baru_dari_permintaan(): void
    {
        $budi = $this->budi();
        $p = PermintaanResetPassword::create(['user_id' => $budi->id, 'status' => 'menunggu']);
        $admin = $this->user('admin');

        $this->actingAs($admin)->get(route('beranda'))->assertSee('1 permintaan reset password');
        $this->actingAs($admin)->get(route('akun.index'))->assertSee('Permintaan reset password')->assertSee('budi · Bidang');
        $this->assertSame(1, $this->actingAs($admin)->getJson(route('beranda.data'))->json('reset_menunggu'));

        $this->actingAs($this->user('bidang'))->post(route('akun.reset.selesai', $p), ['password' => 'Baru#12345', 'password_confirmation' => 'Baru#12345'])->assertForbidden();
        $this->actingAs($admin)->post(route('akun.reset.selesai', $p), ['password' => 'Baru#12345', 'password_confirmation' => 'beda'])->assertSessionHasErrors('password');
        $this->actingAs($admin)->post(route('akun.reset.selesai', $p), ['password' => 'Baru#12345', 'password_confirmation' => 'Baru#12345'])
            ->assertSessionHas('success');

        $p->refresh();
        $this->assertSame('selesai', $p->status);
        $this->assertSame($admin->id, $p->ditangani_oleh);
        $this->assertTrue(Hash::check('Baru#12345', $budi->fresh()->password));
        $this->actingAs($admin)->post(route('akun.reset.selesai', $p), ['password' => 'Lagi#12345', 'password_confirmation' => 'Lagi#12345'])->assertSessionHas('warning');
        $this->actingAs($admin)->get(route('akun.index'))->assertDontSee('id="permintaanReset"', false);
    }

    public function test_admin_menolak_dan_ubah_akun_menutup_permintaan(): void
    {
        $budi = $this->budi();
        $admin = $this->user('admin');
        $p1 = PermintaanResetPassword::create(['user_id' => $budi->id, 'status' => 'menunggu']);
        $this->actingAs($admin)->post(route('akun.reset.tolak', $p1))->assertSessionHas('success');
        $this->assertSame('ditolak', $p1->fresh()->status);

        $p2 = PermintaanResetPassword::create(['user_id' => $budi->id, 'status' => 'menunggu']);
        $this->actingAs($admin)->put(route('akun.update', $budi), ['name' => 'Budi', 'username' => 'budi', 'role' => 'bidang',
            'is_active' => 1, 'password' => 'Ubah#12345', 'password_confirmation' => 'Ubah#12345'])->assertSessionHas('success');
        $this->assertSame('selesai', $p2->fresh()->status);
    }
}
