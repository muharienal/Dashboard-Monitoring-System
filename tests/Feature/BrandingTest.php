<?php

namespace Tests\Feature;

/** Footer "DMS v2 - IT PT Petrokopindo Cipta Selaras © 2026" dan sebutan "DMS v2" di semua halaman. */
class BrandingTest extends DmsTestCase
{
    private const KAKI = 'DMS v2 - IT PT Petrokopindo Cipta Selaras &copy; 2026';

    public function test_footer_dan_judul_di_semua_halaman(): void
    {
        foreach ([route('login'), route('lupa-password')] as $url) {
            $this->get($url)->assertOk()->assertSee(self::KAKI, false)->assertSee('DMS v2');
        }
        $this->get('/tidak-ada-halaman-ini')->assertNotFound()->assertSee(self::KAKI, false)->assertSee('404 · DMS v2');

        $admin = $this->user('admin');
        foreach ([route('beranda'), route('dashboard.perbandingan'), route('kendaraan.profil', $this->k1->id), route('riwayat.index'), route('profil'), route('tv')] as $url) {
            $r = $this->actingAs($admin)->get($url)->assertOk()->assertSee(self::KAKI, false);
            $this->assertMatchesRegularExpression('/<title>[^<]*DMS v2<\/title>/', $r->getContent(), $url);
            $this->assertStringContainsString('aria-label="DMS v2 - Dashboard Management System"', $r->getContent());
        }
        $this->actingAs($admin)->get(route('laporan.bulanan', ['tahun' => 2026, 'bulan' => 10]))->assertOk()
            ->assertSee('Laporan DMS v2')->assertSee(self::KAKI, false)->assertDontSee('Dicetak dari DMS oleh');
    }

    public function test_tidak_ada_sebutan_dms_tanpa_v2_di_tampilan(): void
    {
        $files = array_merge(glob(resource_path('views/**/*.blade.php')), glob(resource_path('views/*.blade.php')),
            [public_path('offline.html'), public_path('manifest.webmanifest')]);
        foreach ($files as $f) {
            $isi = preg_replace('/\{\{--.*?--\}\}|<!--.*?-->/s', '', file_get_contents($f));
            $isi = preg_replace('/X-DMS-Mode|DMS_[A-Z_]+|window\.DMS|DMS\.[a-zA-Z]|DMSAlert/', '', $isi);
            $this->assertDoesNotMatchRegularExpression('/\bDMS\b(?! v2)/', $isi, basename($f));
        }
    }
}
