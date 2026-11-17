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
        $kodeDipakai = $daftarBidang->pluck('kode')->all();

        $filter = $satuBulan
            ? new Filter($awal->year, $awal->month, null, $pilih)
            : new Filter(null, null, null, $pilih, null, $akhir->endOfMonth()->toDateString(), $awal->toDateString());

        // ---- Per bulan: kelengkapan & isian "kosong" dijumlahkan dari setiap bulan dalam rentang ----
        $lengkap = array_fill_keys($kodeDipakai, ['seharusnya' => 0, 'terisi' => 0, 'persen' => null]);
        $kosong = [];
        $perBulan = [];
        for ($m = $awal; $m->lessThanOrEqualTo($akhir); $m = $m->addMonthNoOverflow()) {
            $kb = array_intersect_key($svc->kelengkapanBulan($m->year, $m->month), $lengkap);
            $harusBln = 0;
            $isiBln = 0;
            foreach ($kb as $kode => $l) {
                $isi = min($l['terisi'], $l['seharusnya']);
                $lengkap[$kode]['seharusnya'] += $l['seharusnya'];
                $lengkap[$kode]['terisi'] += $isi;
                $harusBln += $l['seharusnya'];
                $isiBln += $isi;
            }
            foreach (array_intersect_key($svc->isianKosongBulan($m->year, $m->month), $lengkap) as $kode => $k) {
                if ($k['tanggal_kosong'] < 1) {
                    continue;
                }
                $kosong[$kode] ??= ['tanggal_data' => 0, 'tanggal_kosong' => 0, 'contoh' => []];
                $kosong[$kode]['tanggal_data'] += $k['tanggal_data'];
                $kosong[$kode]['tanggal_kosong'] += $k['tanggal_kosong'];
                foreach ($k['contoh'] as $tgl) {
                    $kosong[$kode]['contoh'][] = $satuBulan ? (string) $tgl : $m->setDay((int) $tgl)->translatedFormat('j M');
                }
            }
            if (! $satuBulan) {
                $perBulan[] = ['label' => $m->translatedFormat('F Y'), 'lengkap' => $harusBln > 0 ? round($isiBln / $harusBln * 100, 1) : null]
                    + $svc->availability(new Filter($m->year, $m->month, null, $pilih));
            }
        }
        foreach ($lengkap as &$l) {
            $l['persen'] = $l['seharusnya'] > 0 ? round($l['terisi'] / $l['seharusnya'] * 100, 1) : null;
        }
        unset($l);
        foreach ($kosong as &$k) {
            $k['contoh'] = array_slice($k['contoh'], 0, 3);
        }
        unset($k);
        $totHarus = array_sum(array_column($lengkap, 'seharusnya'));
        $totIsi = array_sum(array_column($lengkap, 'terisi'));

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
            'lengkap' => $lengkap,
            'kosong' => $kosong,
            'lengkapTotal' => $totHarus > 0 ? round($totIsi / $totHarus * 100, 1) : null,
            'perBulan' => $perBulan,
            'jenis' => $svc->availabilityPerJenis($filter),
            'perbaikan' => $svc->maintenance($filter, 10),
            'cetak' => $request->boolean('cetak'),
            'tahunList' => range($now->year, 2024),
            'semuaBidang' => Bidang::ordered(),
        ]);
    }
}
