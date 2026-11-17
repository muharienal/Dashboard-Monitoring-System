<?php

namespace App\Http\Controllers;

use App\Models\PermintaanResetPassword;
use App\Services\DashboardService;
use App\Support\Filter;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        return view('beranda');
    }

    public function data(Request $request, DashboardService $svc)
    {
        $filter = Filter::fromRequest($request);
        $perbaikan = $svc->maintenance($filter);
        $perJenis = collect($svc->availabilityPerJenis($filter))->filter(fn ($r) => $r['unit_hari'] > 0 && $r['availability_rate'] !== null)
            ->sortBy([['availability_rate', 'asc'], ['jenis', 'asc']])->values();
        // Kelengkapan input periode: hanya bila satu bulan dipilih (per tahun terlalu kasar untuk dipantau).
        $kb = ($filter->tahun && $filter->bulan) ? $svc->kelengkapanBulan($filter->tahun, $filter->bulan) : null;
        $kelengkapanPeriode = $kb === null ? null : [
            'per_bidang' => $kb,
            'seharusnya' => array_sum(array_column($kb, 'seharusnya')),
            'terisi' => array_sum(array_map(fn ($r) => min($r['terisi'], $r['seharusnya']), $kb)),
        ];
        if ($kelengkapanPeriode) {
            $kelengkapanPeriode['persen'] = $kelengkapanPeriode['seharusnya'] > 0
                ? round($kelengkapanPeriode['terisi'] / $kelengkapanPeriode['seharusnya'] * 100, 1) : null;
        }
        $t = $this->teks($request, 'tanggal_cek');
        $cek = preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) && checkdate((int) substr($t, 5, 2), (int) substr($t, 8, 2), (int) substr($t, 0, 4))
            ? $t : now()->toDateString();

        return response()->json([
            'filter' => $filter->toArray(),
            'tanggal_cek' => $cek,
            'bidang' => $svc->ringkasanBidang($filter, $cek),
            'availability' => $svc->availability($filter),
            'pembanding' => $svc->pembanding($filter),
            'tren' => $svc->trenDenganKelengkapan(
                $svc->utilitasPeriodik(new Filter($filter->tahun, $filter->bulan), 'hari'),
                $svc->kelengkapanPeriode(new Filter($filter->tahun, $filter->bulan), 'hari'),
            ),
            'kelengkapan' => $svc->kelengkapan([now()->subDay()->toDateString(), now()->toDateString()], $request->user()),
            // Untuk manajemen: unit dengan jam perbaikan terbanyak & jenis dengan availability terendah
            'perbaikan_top' => array_slice($perbaikan, 0, 5),
            'perbaikan_unit' => count($perbaikan),
            'jenis_terendah' => $perJenis->take(5)->all(),
            'jenis_jumlah' => $perJenis->count(),
            'kelengkapan_periode' => $kelengkapanPeriode,
            'reset_menunggu' => $request->user()->isAdmin() ? PermintaanResetPassword::menunggu()->count() : 0,
            'version' => $svc->version(),
        ]);
    }

    public function version(DashboardService $svc)
    {
        return response()->json(['version' => $svc->version()])->header('Cache-Control', 'no-store');
    }
}
