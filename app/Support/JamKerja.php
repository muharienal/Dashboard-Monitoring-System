<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Satu-satunya tempat rumus per-baris dihitung (dulu tersebar di JavaScript tiap halaman + trigger DB).
 *
 *   standby           = 24 - (jalan + rusak + perbaikan + pm)
 *   total_jam_kerja   = jalan + standby + rusak + perbaikan + pm  (selalu 24)
 *   availability_rate = (jalan + standby) / 24 * 100
 *   status utilitas (tepat satu):
 *     JALAN   bila (jalan > 4 dan rusak = perbaikan = pm = 0) atau jalan adalah nilai terbesar (> 0)
 *     STANDBY bila bukan JALAN dan standby adalah nilai terbesar
 *     RUSAK   selain itu (rusak/perbaikan/pm adalah nilai terbesar)
 */
final class JamKerja
{
    public const JAM_PER_HARI = 24;

    public const STATUS = ['JALAN', 'STANDBY', 'RUSAK', 'PERBAIKAN', 'PM'];

    /** @return array<string,int|float> */
    public static function hitung(int $jalan, int $rusak, int $perbaikan, int $pm): array
    {
        foreach (compact('jalan', 'rusak', 'perbaikan', 'pm') as $k => $v) {
            if ($v < 0 || $v > self::JAM_PER_HARI) {
                throw new InvalidArgumentException("{$k} harus 0–24 jam.");
            }
        }
        $terpakai = $jalan + $rusak + $perbaikan + $pm;
        if ($terpakai > self::JAM_PER_HARI) {
            throw new InvalidArgumentException('Total lebih dari 24 jam.');
        }
        $standby = self::JAM_PER_HARI - $terpakai;
        $max = max($jalan, $standby, $rusak, $perbaikan, $pm);

        $uJalan = (($jalan > 4 && $rusak === 0 && $perbaikan === 0 && $pm === 0) || ($jalan === $max && $max > 0)) ? 1 : 0;
        $uStandby = ($uJalan === 0 && ($max === 0 || $standby === $max)) ? 1 : 0;
        $uRusak = ($uJalan === 0 && $uStandby === 0) ? 1 : 0;

        return [
            'jalan' => $jalan,
            'standby' => $standby,
            'rusak' => $rusak,
            'perbaikan' => $perbaikan,
            'pm' => $pm,
            'total_jam_kerja' => $jalan + $standby + $rusak + $perbaikan + $pm,
            'availability_rate' => round(($jalan + $standby) / self::JAM_PER_HARI * 100, 2),
            'utilitas_jalan' => $uJalan,
            'utilitas_standby' => $uStandby,
            'utilitas_rusak' => $uRusak,
        ];
    }

    public static function namaBulan(int $bulan): string
    {
        return ['', 'JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 'JULI',
            'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'][$bulan] ?? '';
    }
}
