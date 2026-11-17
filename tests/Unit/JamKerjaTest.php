<?php

namespace Tests\Unit;

use App\Support\JamKerja;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class JamKerjaTest extends TestCase
{
    public function test_standby_dihitung_otomatis_dan_total_24(): void
    {
        $h = JamKerja::hitung(8, 2, 1, 0);
        $this->assertSame(13, $h['standby']);
        $this->assertSame(24, $h['total_jam_kerja']);
        $this->assertSame(87.5, $h['availability_rate']); // (8+13)/24
    }

    public function test_default_semua_standby(): void
    {
        $h = JamKerja::hitung(0, 0, 0, 0);
        $this->assertSame([0, 1, 0], [$h['utilitas_jalan'], $h['utilitas_standby'], $h['utilitas_rusak']]);
        $this->assertSame(100.0, $h['availability_rate']);
    }

    public function test_jalan_lebih_4_jam_tanpa_kerusakan_dihitung_jalan(): void
    {
        $h = JamKerja::hitung(5, 0, 0, 0); // standby 19 terbesar, tapi aturan "jalan > 4" berlaku
        $this->assertSame(1, $h['utilitas_jalan']);
        $h = JamKerja::hitung(4, 0, 0, 0);
        $this->assertSame(1, $h['utilitas_standby']);
    }

    public function test_jalan_terbesar_walau_ada_rusak(): void
    {
        $h = JamKerja::hitung(12, 2, 0, 0);
        $this->assertSame(1, $h['utilitas_jalan']);
    }

    public function test_rusak_perbaikan_pm_terbesar_menjadi_rusak(): void
    {
        $this->assertSame(1, JamKerja::hitung(0, 0, 16, 0)['utilitas_rusak']);
        $this->assertSame(1, JamKerja::hitung(0, 13, 0, 0)['utilitas_rusak']);
        $this->assertSame(1, JamKerja::hitung(2, 0, 0, 20)['utilitas_rusak']);
    }

    public function test_seri_standby_vs_rusak_dimenangkan_standby(): void
    {
        $h = JamKerja::hitung(0, 12, 0, 0); // standby 12 = rusak 12
        $this->assertSame(1, $h['utilitas_standby']);
    }

    public function test_selalu_tepat_satu_status(): void
    {
        for ($j = 0; $j <= 24; $j += 3) {
            for ($r = 0; $r + $j <= 24; $r += 5) {
                for ($p = 0; $p + $r + $j <= 24; $p += 7) {
                    $h = JamKerja::hitung($j, $r, $p, 0);
                    $this->assertSame(1, $h['utilitas_jalan'] + $h['utilitas_standby'] + $h['utilitas_rusak']);
                    $this->assertSame(24, $h['total_jam_kerja']);
                }
            }
        }
    }

    public function test_total_lebih_24_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JamKerja::hitung(20, 5, 0, 0);
    }

    public function test_nilai_negatif_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JamKerja::hitung(-1, 0, 0, 0);
    }
}
