<?php

namespace App\Http\Controllers;

use App\Models\Bidang;
use App\Services\DashboardService;
use App\Support\Filter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Perbandingan antar bidang: peringkat bulan ini vs bulan lalu. */
class PerbandinganController extends Controller
{
    public const URUT = ['availability' => 'Availability', 'utilisasi' => 'Utilisasi'];

    public function index(Request $request, DashboardService $svc)
    {
        $now = CarbonImmutable::now();
        $tahun = (int) $this->teks($request, 'tahun');
        $bulan = (int) $this->teks($request, 'bulan');
        if ($tahun < 2000 || $tahun > 2100 || $bulan < 1 || $bulan > 12) {
            [$tahun, $bulan] = [$now->year, $now->month];
        }
        $urut = array_key_exists($this->teks($request, 'urut'), self::URUT) ? $this->teks($request, 'urut') : 'availability';
        $awal = CarbonImmutable::create($tahun, $bulan, 1);
        $lalu = $awal->subMonthNoOverflow();

        $baris = Bidang::ordered()->map(function (Bidang $b) use ($svc, $tahun, $bulan) {
            $f = new Filter($tahun, $bulan, null, $b);
            $kini = $svc->availability($f);
            $p = $svc->pembanding($f);
            $sebelum = $p['availability'] ?? null;
            $r1 = fn ($v) => $v === null ? null : round($v, 1);
            $selisih = fn ($a, $b) => ($a === null || $b === null) ? null : round(round($a, 1) - round($b, 1), 1);

            return [
                'kode' => $b->kode,
                'nama' => $b->nama_tampil,
                'unit' => $kini['unit'],
                'availability' => $r1($kini['availability_rate']),
                'utilisasi' => $r1($kini['utilisasi']),
                'd_availability' => $sebelum && $sebelum['unit_hari'] ? $selisih($kini['availability_rate'], $sebelum['availability_rate']) : null,
                'd_utilisasi' => $sebelum && $sebelum['unit_hari'] ? $selisih($kini['utilisasi'], $sebelum['utilisasi']) : null,
                'label_lalu' => $p['label'] ?? null,
            ];
        });
        // Urutkan: nilai tertinggi di atas; bidang tanpa data paling bawah.
        $baris = $baris->sort(fn ($a, $b) => [($b[$urut] !== null), $b[$urut] ?? -1, $a['nama']] <=> [($a[$urut] !== null), $a[$urut] ?? -1, $b['nama']])->values();

        return view('dashboard.perbandingan', [
            'tahun' => $tahun, 'bulan' => $bulan, 'awal' => $awal, 'urut' => $urut, 'baris' => $baris,
            'labelLalu' => $baris->first()['label_lalu'] ?? $lalu->translatedFormat('F Y'),
            'tahunList' => range($now->year, 2024),
        ]);
    }
}
