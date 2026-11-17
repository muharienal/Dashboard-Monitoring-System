<?php

namespace Tests\Feature;

use App\Models\Kendaraan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Profil kendaraan, pencarian, perbandingan bidang, mode TV, riwayat login, keluar otomatis. */
class FiturBaru4Test extends DmsTestCase
{
    private function baris(Kendaraan $k, int $bidangId, string $tgl, int $jalan = 0, int $perbaikan = 0): void
    {
        $sb = 24 - $jalan - $perbaikan;
        DB::table('sheet_data')->insert([
            'tanggal' => $tgl, 'kendaraan_id' => $k->id, 'bidang_id' => $bidangId, 'jenis_kendaraan_id' => $k->jenis_kendaraan_id,
            'jalan' => $jalan, 'standby' => $sb, 'rusak' => 0, 'perbaikan' => $perbaikan, 'pm' => 0, 'total_jam_kerja' => 24,
            'availability_rate' => round(($jalan + $sb) / 24 * 100, 2), 'utilitas_jalan' => $jalan > 4 && ! $perbaikan ? 1 : 0,
            'utilitas_standby' => $jalan > 4 && ! $perbaikan ? 0 : 1, 'utilitas_rusak' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------ Profil kendaraan
    public function test_profil_kendaraan_kalender_dan_ringkasan(): void
    {
        $this->baris($this->k1, $this->anglu->id, '2026-10-30', 16);
        $this->baris($this->k1, $this->anglu->id, '2026-10-31', 0, 20);
        $r = $this->actingAs($this->user('manajemen'))->get(route('kendaraan.profil', ['kendaraan' => $this->k1->id, 'akhir' => '2026-10-31']))->assertOk();
        $r->assertSee('W 1 AA')->assertSee('Kalender status')->assertSee('kal-sel s-jalan', false)->assertSee('kal-sel s-perbaikan', false)
            ->assertSee('kal-sel s-kosong', false)->assertSee('Sab, 31 Okt 2026')->assertSee('Riwayat perubahan');
        $this->assertSame(2, $r->viewData('hariData'));
        $this->assertSame(90, $r->viewData('seharusnya'));
        $this->assertEquals(round((16 + 8 + 4) / 48 * 100, 1), $r->viewData('ar'));
        // Admin melihat riwayat; tautan sel ke form input
        $this->actingAs($this->user('admin'))->get(route('kendaraan.profil', ['kendaraan' => $this->k1->id, 'akhir' => '2026-10-31']))
            ->assertSee('Riwayat perubahan')->assertSee(route('input.show', ['bidang' => $this->anglu, 'tanggal' => '2026-10-31']), false);
        // Kendaraan yang belum beroperasi: hari "tidak beroperasi"
        $this->k2->update(['aktif_sejak' => '2026-10-31']);
        $this->assertSame(1, $this->actingAs($this->user('admin'))->get(route('kendaraan.profil', ['kendaraan' => $this->k2->id, 'akhir' => '2026-10-31']))->viewData('seharusnya'));
        $this->actingAs($this->user('admin'))->get('/kendaraan/999999')->assertNotFound();
        $this->actingAs($this->user('admin'))->get(route('kendaraan.profil', ['kendaraan' => $this->k1->id, 'akhir' => 'x']))->assertOk();
    }

    public function test_cari_nomor_mengabaikan_spasi_dan_huruf(): void
    {
        $u = $this->user('manajemen');
        $h = $this->actingAs($u)->getJson(route('kendaraan.cari', ['q' => 'w1a']))->assertOk()->json('hasil');
        $this->assertSame('W 1 AA', $h[0]['nomor']);
        $this->assertSame(route('kendaraan.profil', $this->k1->id), $h[0]['url']);
        $this->assertCount(0, $this->actingAs($u)->getJson(route('kendaraan.cari', ['q' => 'tidakada']))->json('hasil'));
        $this->assertCount(0, $this->actingAs($u)->getJson(route('kendaraan.cari', ['q' => ['x']]))->json('hasil'));
        // '%' diperlakukan sebagai huruf biasa, bukan wildcard
        $this->assertCount(0, $this->actingAs($u)->getJson(route('kendaraan.cari', ['q' => '%1a']))->json('hasil'));
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->getJson(route('kendaraan.cari', ['q' => 'w']))->assertUnauthorized();
    }

    // ---------------------------------------------------------------- Perbandingan bidang
    public function test_perbandingan_peringkat_dan_kalender(): void
    {
        $this->baris($this->k1, $this->anglu->id, '2026-10-01', 10);
        $this->baris($this->k2, $this->anglu->id, '2026-10-01', 0, 24);
        $this->baris($this->k3, $this->wil2->id, '2026-10-01', 8);
        $r = $this->actingAs($this->user('manajemen'))->get(route('dashboard.perbandingan', ['tahun' => 2026, 'bulan' => 10, 'urut' => 'availability']))->assertOk();
        $baris = $r->viewData('baris');
        $this->assertSame('alber-wil-2', $baris[0]['kode']);   // 100%
        $this->assertSame('angkutan-luar', $baris[1]['kode']); // 50%
        $this->assertSame(50.0, $baris[1]['availability']);
        $kal = collect($r->viewData('kalender'))->keyBy('kode');
        $this->assertSame('lengkap', $kal['angkutan-luar']['sel'][0]['status']);
        $this->assertSame('kosong', $kal['angkutan-luar']['sel'][1]['status']);
        $this->assertSame('tanpa', $kal['angkutan-dalam']['sel'][0]['status']);
        $this->assertSame(31, count($kal['angkutan-luar']['sel']));
        // urut menurut utilisasi & parameter aneh aman
        $this->actingAs($this->user('manajemen'))->get(route('dashboard.perbandingan', ['tahun' => 'x', 'bulan' => [1], 'urut' => 'zzz']))->assertOk();
    }

    // ------------------------------------------------------------------------- Mode TV
    public function test_mode_tv_dan_tetap_masuk(): void
    {
        foreach (['manajemen', 'bidang', 'pemeliharaan'] as $role) {
            $this->actingAs($this->user($role))->get(route('tv'))->assertForbidden();
            $this->actingAs($this->user($role))->postJson(route('tv.ingat'))->assertForbidden();
        }
        $u = $this->user('admin');
        $this->actingAs($u)->get(route('tv'))->assertOk()->assertSee('Mode TV')->assertSessionHas('mode_tv', true);
        $r = $this->actingAs($u)->postJson(route('tv.ingat'))->assertOk();
        $this->assertNotNull($u->fresh()->remember_token);
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get(route('tv'))->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------- Riwayat login
    public function test_riwayat_login_berhasil_gagal_nonaktif_keluar(): void
    {
        $budi = User::create(['name' => 'Budi', 'username' => 'budi', 'password' => 'Rahasia#123', 'role' => 'bidang', 'is_active' => true]);
        User::create(['name' => 'Off', 'username' => 'off', 'password' => 'Rahasia#123', 'role' => 'bidang', 'is_active' => false]);
        $hp = ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/131.0 Mobile Safari/537.36'];
        $this->post(route('login.attempt'), ['username' => 'budi', 'password' => 'salah'], $hp);
        $this->post(route('login.attempt'), ['username' => 'off', 'password' => 'Rahasia#123'], $hp);
        $this->post(route('login.attempt'), ['username' => 'siapa', 'password' => 'x'], $hp);
        $this->post(route('login.attempt'), ['username' => 'budi', 'password' => 'Rahasia#123'], $hp)->assertRedirect(route('beranda'));
        $this->post(route('logout'));

        $r = DB::table('riwayat_login')->orderBy('id')->get();
        $this->assertSame(['gagal', 'nonaktif', 'gagal', 'berhasil', 'keluar'], $r->pluck('hasil')->all());
        $this->assertSame($budi->id, $r[0]->user_id);
        $this->assertNull($r[2]->user_id);
        $this->assertSame('siapa', $r[2]->isian);
        $this->assertSame('Chrome · Android (HP)', $r[3]->perangkat);

        $admin = $this->user('admin');
        $this->actingAs($admin)->get(route('riwayat.index', ['tab' => 'login']))->assertOk()->assertSee('Gagal masuk')->assertSee('diketik: siapa')->assertSee('Chrome · Android (HP)');
        $this->actingAs($admin)->get(route('riwayat.index', ['tab' => 'login', 'hasil' => 'berhasil']))->assertOk()->assertDontSee('diketik: siapa');
        $this->assertSame(5, $this->actingAs($admin)->getJson(route('riwayat.index', ['tab' => 'login', 'format' => 'xlsx']))->json('jumlah'));
        $this->actingAs($this->user('manajemen'))->get(route('riwayat.index', ['tab' => 'login']))->assertForbidden();
        $this->actingAs($budi)->get(route('profil'))->assertOk()->assertSee('Aktivitas login akun Anda')->assertSee('Chrome · Android (HP)');
    }

    // ------------------------------------------------------------------ Keluar otomatis
    public function test_keluar_otomatis_setelah_tidak_aktif(): void
    {
        config(['dms.idle_menit' => 120]);
        $u = $this->user('manajemen');
        $lama = now()->subMinutes(121)->getTimestamp();
        // Halaman biasa → keluar, dengan pesan dan catatan riwayat
        $this->actingAs($u)->withSession(['aktif_terakhir' => $lama])->get(route('beranda'))
            ->assertRedirect(route('login'))->assertSessionHas('warning', fn ($p) => str_contains($p, 'tidak aktif lebih dari 2 jam'));
        $this->assertGuest();
        $this->assertSame('keluar_otomatis', DB::table('riwayat_login')->latest('id')->value('hasil'));
        // Permintaan data (JSON) → 401
        $this->actingAs($u)->withSession(['aktif_terakhir' => $lama])->getJson(route('beranda.data'))->assertUnauthorized();
        // Masih dalam batas → aman; polling JSON tidak memperbarui waktu aktif, POST /api/aktif memperbarui
        $hampir = now()->subMinutes(100)->getTimestamp();
        $this->actingAs($u)->withSession(['aktif_terakhir' => $hampir])->getJson(route('version'))->assertOk()->assertSessionHas('aktif_terakhir', $hampir);
        $this->actingAs($u)->withSession(['aktif_terakhir' => $hampir])->postJson(route('aktif'))->assertNoContent()
            ->assertSessionHas('aktif_terakhir', fn ($t) => $t >= now()->subMinute()->getTimestamp());
        // Mode TV dikecualikan
        $this->actingAs($u)->withSession(['aktif_terakhir' => $lama])->getJson(route('beranda.data'), ['X-DMS-Mode' => 'tv'])->assertOk();
        // Logout karena tidak aktif dari browser
        $this->actingAs($u)->withSession(['aktif_terakhir' => now()->getTimestamp()])->post(route('logout'), ['alasan' => 'tidak_aktif'])
            ->assertSessionHas('warning');
        // Fitur bisa dimatikan
        config(['dms.idle_menit' => 0]);
        $this->actingAs($u)->withSession(['aktif_terakhir' => $lama])->get(route('beranda'))->assertOk();
    }
}
