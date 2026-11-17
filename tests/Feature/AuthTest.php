<?php

namespace Tests\Feature;

use App\Models\User;

class AuthTest extends DmsTestCase
{
    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->getJson(route('api.dashboard.availability'))->assertUnauthorized();
        $this->postJson(route('input.simpan', $this->anglu), [])->assertUnauthorized();
    }

    public function test_login_berhasil_mencatat_login_terakhir(): void
    {
        $u = $this->user('admin');
        $this->post(route('login.attempt'), ['username' => $u->username, 'password' => 'Rahasia#123'])->assertRedirect(route('beranda'));
        $this->assertNotNull($u->fresh()->last_login_at);
    }

    public function test_hash_bcrypt_lama_tetap_bisa_login(): void
    {
        // Hash sistem lama: bcrypt cost 10 ($2y$10$...), ditulis langsung seperti perintah import
        $u = $this->user('admin');
        \Illuminate\Support\Facades\DB::table('users')->where('id', $u->id)->update(['password' => password_hash('passwordlama', PASSWORD_BCRYPT, ['cost' => 10])]);
        config(['hashing.bcrypt.rounds' => 12]);
        $this->post(route('login.attempt'), ['username' => $u->username, 'password' => 'passwordlama'])->assertRedirect(route('beranda'));
    }

    public function test_pesan_validasi_bahasa_indonesia(): void
    {
        $this->post(route('login.attempt'), ['username' => '', 'password' => ''])->assertSessionHasErrors(['username' => 'Nama pengguna wajib diisi.']);
    }

    public function test_login_nama_pengguna_tidak_peka_huruf_besar_dan_spasi(): void
    {
        $u = $this->user('admin');
        $u->forceFill(['username' => 'anglu'])->save();
        $this->post(route('login.attempt'), ['username' => '  AngLu ', 'password' => 'Rahasia#123'])->assertRedirect(route('beranda'));
        $this->assertAuthenticatedAs($u);
    }

    public function test_isian_lama_berbentuk_email_tetap_bisa_login(): void
    {
        $u = $this->user('admin');
        $u->forceFill(['username' => 'anglu'])->save();
        $this->post(route('login.attempt'), ['username' => 'anglu@gmail.com', 'password' => 'Rahasia#123'])->assertRedirect(route('beranda'));
        $this->assertAuthenticatedAs($u);
    }

    public function test_halaman_login_punya_placeholder(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('placeholder="Masukkan nama pengguna"', false)
            ->assertSee('placeholder="Masukkan password"', false)
            ->assertSee('Contoh: admin (tanpa @gmail.com)');
    }

    public function test_login_gagal_dan_akun_nonaktif_ditolak(): void
    {
        $u = $this->user('admin');
        $this->post(route('login.attempt'), ['username' => $u->username, 'password' => 'salah'])->assertSessionHasErrors('username');
        $u->update(['is_active' => false]);
        $this->post(route('login.attempt'), ['username' => $u->username, 'password' => 'Rahasia#123'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_akun_dinonaktifkan_langsung_keluar(): void
    {
        $u = $this->user('bidang');
        $u->update(['is_active' => false]);
        $this->actingAs($u)->get('/')->assertRedirect(route('login'));
    }

    public function test_hak_akses_menu(): void
    {
        $m = $this->user('manajemen');
        $this->actingAs($m)->get(route('dashboard.availability'))->assertOk();
        $this->actingAs($m)->get(route('input.show', $this->anglu))->assertForbidden();
        $this->actingAs($m)->get(route('master.kendaraan'))->assertForbidden();
        $this->actingAs($m)->get(route('akun.index'))->assertForbidden();

        $b = $this->user('bidang');
        $this->actingAs($b)->get(route('input.show', $this->anglu))->assertOk();
        $this->actingAs($b)->post(route('akun.store'), [])->assertForbidden();
    }

    public function test_user_bidang_bisa_dibatasi_ke_satu_bidang(): void
    {
        $b = $this->user('bidang', $this->anglu->id);
        $this->actingAs($b)->get(route('input.show', $this->anglu))->assertOk();
        $this->actingAs($b)->get(route('input.show', $this->wil2))->assertForbidden();
    }

    public function test_url_lama_dialihkan(): void
    {
        $this->get('/sheetAnglu')->assertRedirect('/input/angkutan-luar');
        $this->get('/dashboardAlberWil3.php')->assertRedirect('/dashboard/summary/alber-wil-3');
    }

    public function test_admin_tidak_bisa_hapus_atau_turunkan_diri_sendiri(): void
    {
        $a = $this->user('admin');
        $this->actingAs($a)->delete(route('akun.destroy', $a))->assertSessionHas('error');
        $this->actingAs($a)->put(route('akun.update', $a), ['name' => 'x', 'username' => $a->username, 'role' => 'manajemen', 'is_active' => 1])->assertSessionHas('error');
        $this->assertSame('admin', $a->fresh()->role);
    }

    public function test_admin_kelola_akun(): void
    {
        $a = $this->user('admin');
        $this->actingAs($a)->post(route('akun.store'), ['name' => 'Baru', 'username' => 'baru', 'role' => 'pemeliharaan',
            'password' => 'Rahasia#123', 'password_confirmation' => 'Rahasia#123', 'is_active' => 1])->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['username' => 'baru', 'role' => 'pemeliharaan', 'bidang_id' => null]);
        $this->actingAs($a)->post(route('akun.store'), ['name' => 'X', 'username' => 'baru', 'role' => 'admin',
            'password' => 'Rahasia#123', 'password_confirmation' => 'Rahasia#123'])->assertSessionHasErrors('username');
    }

    public function test_akun_memakai_nama_pengguna_bukan_email(): void
    {
        $a = $this->user('admin');
        $isi = fn ($u) => ['name' => 'Rina', 'username' => $u, 'role' => 'manajemen', 'is_active' => 1, 'password' => 'Rahasia#123', 'password_confirmation' => 'Rahasia#123'];
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('users', 'email'));

        // Huruf besar & spasi di tepi dirapikan otomatis
        $this->actingAs($a)->post(route('akun.store'), $isi('  Rina.S '))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['username' => 'rina.s', 'name' => 'Rina']);
        // Spasi di tengah, karakter aneh, bentuk email, dan duplikat ditolak dengan pesan jelas
        foreach (['rina s', 'rina!', 'rina@gmail.com', 'r'] as $salah) {
            $this->actingAs($a)->post(route('akun.store'), $isi($salah))->assertSessionHasErrors('username');
        }
        $this->actingAs($a)->post(route('akun.store'), $isi('RINA.S'))->assertSessionHasErrors(['username' => 'Nama pengguna ini sudah dipakai akun lain.']);
        // Akun baru langsung bisa login dengan nama penggunanya
        auth()->logout();
        $this->post(route('login.attempt'), ['username' => 'rina.s', 'password' => 'Rahasia#123'])->assertRedirect(route('beranda'));
        // Halaman akun & profil menampilkan nama pengguna
        $this->actingAs($a)->get(route('akun.index'))->assertSee('Nama pengguna')->assertSee('<code class="uname">rina.s</code>', false)->assertDontSee('>Email<', false);
        $this->actingAs(\App\Models\User::where('username', 'rina.s')->first())->get(route('profil'))->assertSee('rina.s');
    }
}
