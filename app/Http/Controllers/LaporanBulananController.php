<?php

namespace App\Http\Controllers;

use App\Models\Bidang;
use App\Services\DashboardService;
use App\Support\Filter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Laporan operasional siap cetak / simpan PDF, dengan kop perusahaan.
 * Periode: satu bulan (tahun & bulan) atau beberapa bulan (dari & ke, format YYYY-MM, maks. 24 bulan).
 * Bidang: semua atau satu bidang; petugas bidang selalu dikunci ke bidangnya sendiri.
 */
class LaporanBulananController extends Controller
{
    public function show(Request $request, DashboardService $svc)
    {
        $now = CarbonImmutable::now();

        // ---- Periode ----
        $rentang = Filter::rentang($request);
        if (! $rentang) {
            $tahun = (int) $this->teks($request, 'tahun');
            $bulan = (int) $this->teks($request, 'bulan');
            if ($tahun < 2000 || $tahun > 2100 || $bulan < 1 || $bulan > 12) {
                [$tahun, $bulan] = [$now->year, $now->month];
            }
            $rentang = [CarbonImmutable::create($tahun, $bulan, 1)->startOfDay(), CarbonImmutable::create($tahun, $bulan, 1)->startOfDay()];
        }
        [$awal, $akhir] = $rentang;
        $satuBulan = $awal->equalTo($akhir);

        // ---- Bidang ----
        $terkunci = Filter::bidangTerkunci($request->user());
        $pilih = $terkunci ?? (($kode = $this->teks($request, 'bidang')) !== '' ? Bidang::where('kode', $kode)->first() : null);
        $daftarBidang = $pilih ? collect([$pilih]) : Bidang::ordered();

        $filter = $satuBulan
            ? new Filter($awal->year, $awal->month, null, $pilih)
            : new Filter(null, null, null, $pilih, null, $akhir->endOfMonth()->toDateString(), $awal->toDateString());

        // ---- Per bulan (rentang lebih dari satu bulan) ----
        $perBulan = [];
        if (! $satuBulan) {
            for ($m = $awal; $m->lessThanOrEqualTo($akhir); $m = $m->addMonthNoOverflow()) {
                $perBulan[] = ['label' => $m->translatedFormat('F Y')] + $svc->availability(new Filter($m->year, $m->month, null, $pilih));
            }
        }

        return view('laporan.bulanan', [
            'awal' => $awal,
            'akhir' => $akhir,
            'satuBulan' => $satuBulan,
            'periode' => Filter::labelRentang($awal, $akhir),
            'berjalan' => $akhir->isSameMonth($now),
            'belumMulai' => $awal->greaterThan($now->startOfMonth()),
            'pilih' => $pilih,
            'terkunci' => $terkunci,
            'total' => $svc->availability($filter),
            'bidang' => $daftarBidang->map(fn (Bidang $b) => ['nama' => $b->nama_tampil, 'kode' => $b->kode] + $svc->availability($filter->denganBidang($b)))->all(),
            'perBulan' => $perBulan,
            'jenis' => $svc->availabilityPerJenis($filter),
            'perbaikan' => $svc->maintenance($filter, 10),
            'cetak' => $request->boolean('cetak'),
            'tahunList' => range($now->year, 2024),
            'semuaBidang' => Bidang::ordered(),
        ]);
    }
}
