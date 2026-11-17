<?php

namespace App\Http\Controllers;

use App\Models\Kendaraan;
use App\Models\SheetData;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Profil satu kendaraan: kalender status 90 hari, ringkasan jam, hari perbaikan, riwayat. */
class ProfilKendaraanController extends Controller
{
    public const HARI = 90;

    /** Urutan pemenang bila jam seri: aktivitas & gangguan lebih informatif daripada Standby. */
    private const URUTAN = ['jalan', 'rusak', 'perbaikan', 'pm', 'standby'];

    public function show(Request $request, Kendaraan $kendaraan)
    {
        $kendaraan->load('jenis.bidang');
        $hariIni = CarbonImmutable::today();
        $t = $this->teks($request, 'akhir');
        $akhir = preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) && checkdate((int) substr($t, 5, 2), (int) substr($t, 8, 2), (int) substr($t, 0, 4))
            ? CarbonImmutable::parse($t)->startOfDay() : $hariIni;
        if ($akhir->greaterThan($hariIni)) {
            $akhir = $hariIni;
        }
        $awal = $akhir->subDays(self::HARI - 1);

        $data = SheetData::query()->where('kendaraan_id', $kendaraan->id)
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->with('updater:id,name')->get()->keyBy(fn ($r) => $r->tanggal->toDateString());

        // Sel kalender per tanggal
        $hari = [];
        $jam = array_fill_keys(self::URUTAN, 0);
        $uj = 0;
        $seharusnya = 0;
        for ($d = $awal; $d->lessThanOrEqualTo($akhir); $d = $d->addDay()) {
            $tgl = $d->toDateString();
            $aktif = $kendaraan->aktifPada($tgl);
            $r = $data->get($tgl);
            if ($aktif) {
                $seharusnya++;
            }
            if ($r) {
                $nilai = [];
                foreach (self::URUTAN as $k) {
                    $nilai[$k] = (int) $r->{$k};
                    $jam[$k] += (int) $r->{$k};
                }
                $uj += (int) $r->utilitas_jalan;
                $max = max($nilai);
                $dominan = collect(self::URUTAN)->first(fn ($k) => $nilai[$k] === $max);
                $hari[$tgl] = ['status' => $dominan, 'nilai' => $nilai, 'oleh' => $r->updater?->name];
            } else {
                $hari[$tgl] = ['status' => $aktif ? 'kosong' : 'luar', 'nilai' => null, 'oleh' => null];
            }
        }
        $total = array_sum($jam);
        $hariData = $data->count();

        // Minggu (kolom) x hari Senin..Minggu (baris), seperti kalender kontribusi.
        $minggu = [];
        for ($m = $awal->startOfWeek(); $m->lessThanOrEqualTo($akhir); $m = $m->addWeek()) {
            $kolom = [];
            for ($i = 0; $i < 7; $i++) {
                $tgl = $m->addDays($i)->toDateString();
                $kolom[] = ['tanggal' => $tgl, 'sel' => $hari[$tgl] ?? null];
            }
            $minggu[] = ['awal' => $m, 'hari' => $kolom];
        }

        $gangguan = $data->filter(fn ($r) => $r->rusak > 0 || $r->perbaikan > 0 || $r->pm > 0)
            ->sortByDesc(fn ($r) => $r->tanggal->toDateString())->values();

        $user = $request->user();
        $admin = $user->isAdmin();
        // Riwayat input: semua role, petugas bidang hanya bila unit ini milik bidangnya. Riwayat master: admin saja.
        $batas = $user->bidangRiwayat();
        $lihatRiwayat = $user->bolehLihatRiwayat($kendaraan->jenis?->bidang_id);
        $riwayatInput = $lihatRiwayat ? DB::table('riwayat_input')->where('kendaraan_id', $kendaraan->id)
            ->when($batas !== null, fn ($w) => $w->where('bidang_id', $batas))->orderByDesc('created_at')->orderByDesc('id')->limit(15)->get() : collect();
        $riwayatMaster = $admin ? DB::table('riwayat_master')->where('objek', 'kendaraan')->where('objek_id', $kendaraan->id)->orderByDesc('created_at')->orderByDesc('id')->limit(10)->get() : collect();
        $rentangData = DB::table('sheet_data')->where('kendaraan_id', $kendaraan->id)->selectRaw('MIN(tanggal) a, MAX(tanggal) b, COUNT(*) n')->first();

        return view('kendaraan.profil', [
            'k' => $kendaraan,
            'bidang' => $kendaraan->jenis?->bidang,
            'awal' => $awal,
            'akhir' => $akhir,
            'hariIni' => $hariIni,
            'minggu' => $minggu,
            'jam' => $jam,
            'total' => $total,
            'ar' => $total > 0 ? round(($jam['jalan'] + $jam['standby']) / $total * 100, 1) : null,
            'utilisasi' => $hariData > 0 ? round($uj / $hariData * 100, 1) : null,
            'hariData' => $hariData,
            'seharusnya' => $seharusnya,
            'gangguan' => $gangguan,
            'riwayatInput' => $riwayatInput,
            'riwayatMaster' => $riwayatMaster,
            'lihatRiwayat' => $lihatRiwayat,
            'rentangData' => $rentangData,
            'bisaInput' => $kendaraan->jenis?->bidang && $request->user()->canInput($kendaraan->jenis->bidang),
            'admin' => $admin,
        ]);
    }

    /** Pencarian nomor kendaraan untuk kotak cari di bagian atas. */
    public function cari(Request $request)
    {
        $q = mb_substr($this->teks($request, 'q'), 0, 50);
        if (mb_strlen($q) < 1) {
            return response()->json(['hasil' => []]);
        }
        // Abaikan spasi saat mencocokkan: "w9242" menemukan "W 9242 UM".
        $rapat = str_replace(' ', '', mb_strtoupper($q));
        $like = '%'.addcslashes($rapat, '%_\\').'%';
        $hasil = Kendaraan::query()
            ->join('jenis_kendaraan as j', 'j.id', '=', 'kendaraan.jenis_kendaraan_id')
            ->join('bidang as b', 'b.id', '=', 'j.bidang_id')
            ->whereRaw("REPLACE(UPPER(kendaraan.nomor_kendaraan), ' ', '') LIKE ?", [$like])
            ->orderByRaw("CASE WHEN REPLACE(UPPER(kendaraan.nomor_kendaraan), ' ', '') = ? THEN 0 WHEN REPLACE(UPPER(kendaraan.nomor_kendaraan), ' ', '') LIKE ? THEN 1 ELSE 2 END", [$rapat, addcslashes($rapat, '%_\\').'%'])
            ->orderByDesc('kendaraan.is_active')->orderBy('kendaraan.nomor_kendaraan')
            ->limit(8)
            ->get(['kendaraan.id', 'kendaraan.nomor_kendaraan', 'kendaraan.is_active', 'j.nama as jenis', 'b.nama_tampil as bidang']);

        return response()->json(['hasil' => $hasil->map(fn ($k) => [
            'id' => $k->id, 'nomor' => $k->nomor_kendaraan, 'jenis' => $k->jenis, 'bidang' => $k->bidang,
            'aktif' => (bool) $k->is_active, 'url' => route('kendaraan.profil', $k->id),
        ])]);
    }
}
