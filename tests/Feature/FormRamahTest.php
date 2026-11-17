<?php

namespace Tests\Feature;

/** Semua kolom isian punya placeholder; semua kolom password punya tombol lihat/sembunyikan. */
class FormRamahTest extends DmsTestCase
{
    public function test_semua_kolom_isian_punya_placeholder(): void
    {
        $kurang = [];
        foreach (array_merge(glob(resource_path('views/*.blade.php')), glob(resource_path('views/**/*.blade.php'))) as $f) {
            preg_match_all('/<(input|textarea)\b((?:->|[^>])*)>/s', file_get_contents($f), $m, PREG_SET_ORDER);
            foreach ($m as [$tag, $el, $attr]) {
                $type = preg_match('/type="([^"]+)"/', $attr, $t) ? $t[1] : 'text';
                if (in_array($type, ['hidden', 'checkbox', 'radio', 'submit', 'button', 'file', 'date'], true)) {
                    continue;
                }
                if (! str_contains($attr, 'placeholder=')) {
                    $kurang[] = basename($f).': '.mb_substr(trim($tag), 0, 90);
                }
            }
        }
        $this->assertSame([], $kurang);
    }

    public function test_tombol_lihat_password_dimuat_di_halaman_berpassword(): void
    {
        $skrip = 'assets/js/password.js';
        $this->get(route('login'))->assertSee($skrip, false);
        $this->actingAs($this->user('admin'))->get(route('profil'))->assertSee($skrip, false);
        $this->actingAs($this->user('admin'))->get(route('akun.index'))->assertSee($skrip, false);
    }
}
