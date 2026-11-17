<?php

namespace App\Http\Controllers;

use App\Models\Bidang;
use App\Services\DashboardService;
use App\Support\Filter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Perbandingan antar bidang: peringkat bulan ini vs bulan lalu, dan kalender kelengkapan input. */
class PerbandinganController extends Controller
{
    public const URUT = ['availability' => 'Availability', 'utilisasi' => 'Utilisasi', 'kelengkapan' => 'Kelengkapan input'];

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

        $lengkap = $svc->kelengkapanBulan($tahun, $bulan);
        $lengkapLalu = $svc->kelengkapanBulan($lalu->year, $lalu->month);
        $kosong = $svc->isianKosongBulan($tahun, $bulan);

        $baris = Bidang::ordered()->map(function (Bidang $b) use ($svc, $tahun, $bulan, $lengkap, $lengkapLalu, $kosong) {
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
                'kelengkapan' => $lengkap[$b->kode]['persen'] ?? null,
                'd_availability' => $sebelum && $sebelum['unit_hari'] ? $selisih($kini['availability_rate'], $sebelum['availability_rate']) : null,
                'd_utilisasi' => $sebelum && $sebelum['unit_hari'] ? $selisih($kini['utilisasi'], $sebelum['utilisasi']) : null,
                'd_kelengkapan' => $selisih($lengkap[$b->kode]['persen'] ?? null, $lengkapLalu[$b->kode]['persen'] ?? null),
                'label_lalu' => $p['label'] ?? null,
                'kosong' => $kosong[$b->kode]['tanggal_kosong'] ?? 0,
            ];
        });
        // Urutkan: nilai tertinggi di atas; bidang tanpa data paling bawah.
        $baris = $baris->sort(fn ($a, $b) => [($b[$urut] !== null), $b[$urut] ?? -1, $a['nama']] <=> [($a[$urut] !== null), $a[$urut] ?? -1, $b['nama']])->values();

        return view('dashboard.perbandingan', [
            'tahun' => $tahun, 'bulan' => $bulan, 'awal' => $awal, 'urut' => $urut, 'baris' => $baris,
            'labelLalu' => $baris->first()['label_lalu'] ?? $lalu->translatedFormat('F Y'),
            'kalender' => $this->kalender($svc, $tahun, $bulan),
            'tahunList' => range($now->year, 2024),
        ]);
    }

    /** Kotak per tanggal per bidang: lengkap / sebagian / kosong / belum waktunya, plus tanda isian "kosong". */
    private function kalender(DashboardService $svc, int $tahun, int $bulan): array
    {
        $awal = CarbonImmutable::create($tahun, $bulan, 1);
        $hariIni = CarbonImmutable::today()->toDateString();
        $curiga = DB::table('sheet_data')->whereBetween('tanggal', [$awal->toDateString(), $awal->endOfMonth()->toDateString()])
            ->selectRaw('bidang_id, tanggal, COUNT(*) n, SUM(CASE WHEN jalan = 0 AND rusak = 0 THEN 1 ELSE 0 END) k')
            ->groupBy('bidang_id', 'tanggal')->get()
            ->filter(fn ($r) => (int) $r->n > 0 && (int) $r->k === (int) $r->n)
            ->map(fn ($r) => $r->bidang_id.'|'.substr((string) $r->tanggal, 0, 10))->flip();
        $user = request()->user();

        return Bidang::ordered()->map(function (Bidang $b) use ($svc, $tahun, $bulan, $awal, $hariIni, $curiga, $user) {
            $kl = $svc->kelengkapanPeriode(new Filter($tahun, $bulan, null, $b), 'hari');
            $sel = [];
            for ($d = 1; $d <= $awal->daysInMonth; $d++) {
                $tgl = $awal->day($d)->toDateString();
                $k = $kl[$tgl] ?? null;
                $status = match (true) {
                    $tgl > $hariIni => 'nanti',
                    ! $k || $k['unit'] === 0 => 'tanpa',
                    $k['terisi'] >= $k['unit'] => 'lengkap',
                    $k['terisi'] > 0 => 'sebagian',
                    default => 'kosong',
                };
                $sel[] = ['tanggal' => $tgl, 'hari' => $d, 'status' => $status, 'unit' => $k['unit'] ?? 0, 'terisi' => $k['terisi'] ?? 0,
                    'curiga' => isset($curiga[$b->id.'|'.$tgl])];
            }

            return ['kode' => $b->kode, 'nama' => $b->nama_tampil, 'bidang' => $b, 'bisa_input' => $user->canInput($b), 'sel' => $sel];
        })->all();
    }
}
