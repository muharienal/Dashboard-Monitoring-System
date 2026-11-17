<?php

namespace App\Http\Controllers;

use App\Models\Bidang;
use App\Models\JenisKendaraan;
use App\Services\DashboardService;
use App\Support\Filter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pengganti embed Tableau Public. Setiap halaman = view Blade + endpoint JSON (realtime polling).
 */
class DashboardController extends Controller
{
    public function __construct(private DashboardService $svc) {}

    private function opsi(): array
    {
        $tahun = DB::table('sheet_data')->selectRaw('MIN(tanggal) a, MAX(tanggal) b')->first();
        $min = $tahun?->a ? (int) substr($tahun->a, 0, 4) : (int) date('Y');
        $max = max((int) date('Y'), $tahun?->b ? (int) substr($tahun->b, 0, 4) : (int) date('Y'));

        return [
            'tahunList' => range($max, $min),
            'bidangList' => Bidang::ordered(),
            'jenisList' => JenisKendaraan::query()->distinct()->orderBy('nama')->pluck('nama'),
            'jenisPerBidang' => Bidang::with('jenisKendaraan:id,bidang_id,nama')->get()
                ->mapWithKeys(fn ($b) => [$b->kode => $b->jenisKendaraan->pluck('nama')]),
        ];
    }

    private function json(array $payload, Filter $f)
    {
        return response()->json($payload + ['filter' => $f->toArray(), 'version' => $this->svc->version()])
            ->header('Cache-Control', 'no-store');
    }

    // ---- Utilisasi Assets (UtilitasUnit/Dashboard3) ----
    public function utilisasi()
    {
        return view('dashboard.utilisasi', $this->opsi());
    }

    public function utilisasiData(Request $r)
    {
        $f = Filter::fromRequest($r);

        return $this->json(['jenis' => $this->svc->utilisasiPerJenis($f)], $f);
    }

    // ---- Availability Assets (AVAILABILITYRATEFIXSQL-VER2/Dashboard1) ----
    public function availability()
    {
        return view('dashboard.availability', $this->opsi());
    }

    public function availabilityData(Request $r)
    {
        $f = Filter::fromRequest($r);

        return $this->json(['total' => $this->svc->availability($f), 'jenis' => $this->svc->availabilityPerJenis($f)], $f);
    }

    // ---- Maintenance Assets (MAINTENANCE-VER-2-SQL/DashMaintenanceAssets) ----
    public function maintenance()
    {
        return view('dashboard.maintenance', $this->opsi());
    }

    public function maintenanceData(Request $r)
    {
        $f = Filter::fromRequest($r);
        $out = [];
        // Bila satu bidang dipilih (atau dikunci untuk petugas bidang), hanya bidang itu yang dikirim.
        foreach (Bidang::ordered()->filter(fn ($b) => ! $f->bidang || $b->id === $f->bidang->id) as $b) {
            $fb = $f->denganBidang($b);
            $out[] = ['kode' => $b->kode, 'nama' => $b->nama_tampil, 'data' => $this->svc->maintenance($fb)];
        }

        return $this->json(['bidang' => $out], $f);
    }

    // ---- Laporan: gabungan Detail Reports (REPORTS-VER-2-SQL) + General Reports ----
    public function laporan(Request $r)
    {
        return view('dashboard.laporan', $this->opsi() + ['tab' => $r->input('tab') === 'detail' ? 'detail' : 'rekap']);
    }

    public function reportData(Request $r)
    {
        $f = Filter::fromRequest($r);

        return $this->json(['report' => $this->svc->detailReport($f)], $f);
    }

    public function generalData(Request $r)
    {
        $f = Filter::fromRequest($r);

        return $this->json(['rekap' => $this->svc->rekap($f), 'total' => $this->svc->availability($f)], $f);
    }

    // ---- Dashboard Summary per bidang (DASHBOARD ANGLU/ANGDAL/ALBERWIL1/2/3) ----
    public function summary(Bidang $bidang)
    {
        return view('dashboard.summary', $this->opsi() + ['bidang' => $bidang]);
    }

    public function summaryData(Request $r, Bidang $bidang)
    {
        $f = Filter::fromRequest($r, $bidang);
        // Bar UTILITAS per bulan menampilkan seluruh bulan pada tahun terpilih (sesuai Tableau: MY(tanggal)).
        $fTahun = new Filter($f->tahun, null, null, $bidang, $f->jenis);

        return $this->json([
            'utilitas_bulanan' => $this->svc->utilitasPeriodik($fTahun, 'bulan'),
            'tren' => $this->svc->trenDenganKelengkapan(
                $this->svc->utilitasPeriodik($f, $f->bulan ? 'hari' : 'bulan'),
                $this->svc->kelengkapanPeriode($f, $f->bulan ? 'hari' : 'bulan'),
            ),
            'tren_mode' => $f->bulan ? 'hari' : 'bulan',
            'maintenance' => $this->svc->maintenance($f, (int) config('dms.top_n_maintenance', 30)),
            'availability' => $this->svc->availability($f),
            'pembanding' => $this->svc->pembanding($f),
        ], $f);
    }
}
