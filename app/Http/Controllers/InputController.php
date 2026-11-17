<?php

namespace App\Http\Controllers;

use App\Models\Bidang;
use App\Services\DashboardService;
use App\Services\InputService;
use Illuminate\Http\Request;

class InputController extends Controller
{
    public function __construct(private InputService $svc, private DashboardService $dash) {}

    /** Grid + versi data (baseline realtime yang konsisten dengan isi grid). */
    private function grid(Bidang $bidang, string $tanggal, $user): array
    {
        $version = $this->dash->version();

        return $this->svc->grid($bidang, $tanggal, $user) + ['version' => $version];
    }

    private function authorizeBidang(Request $request, Bidang $bidang): void
    {
        abort_unless($request->user()->canInput($bidang), 403, 'Anda tidak punya akses input bidang ini.');
    }

    public function show(Request $request, Bidang $bidang)
    {
        $this->authorizeBidang($request, $bidang);

        return view('input.sheet', [
            'bidang' => $bidang,
            'kolomEdit' => $request->user()->editableColumns($bidang),
        ]);
    }

    public function data(Request $request, Bidang $bidang)
    {
        $this->authorizeBidang($request, $bidang);
        $data = $request->validate(['tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']],
            ['tanggal.before_or_equal' => 'Tanggal tidak boleh melewati hari ini.']);

        return response()->json($this->grid($bidang, $data['tanggal'], $request->user()));
    }

    /** Maksimal rentang ekspor Excel di halaman input. */
    public const MAKS_HARI_EKSPOR = 366;

    /** Semua kendaraan bidang ini per tanggal pada rentang (yang belum diisi bernilai kosong) — Ekspor Excel di halaman input. */
    public function ekspor(Request $request, Bidang $bidang)
    {
        $this->authorizeBidang($request, $bidang);
        $data = $request->validate([
            'dari' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'sampai' => ['required', 'date_format:Y-m-d', 'after_or_equal:dari', 'before_or_equal:today'],
        ], [
            'sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
            'dari.before_or_equal' => 'Tanggal tidak boleh melewati hari ini.',
            'sampai.before_or_equal' => 'Tanggal tidak boleh melewati hari ini.',
        ], ['dari' => 'Dari tanggal', 'sampai' => 'Sampai tanggal']);
        $hari = \Carbon\Carbon::parse($data['dari'])->diffInDays(\Carbon\Carbon::parse($data['sampai'])) + 1;
        if ($hari > self::MAKS_HARI_EKSPOR) {
            return response()->json(['message' => 'Rentang maksimal '.self::MAKS_HARI_EKSPOR.' hari.', 'errors' => ['sampai' => ['Rentang maksimal '.self::MAKS_HARI_EKSPOR.' hari.']]], 422);
        }

        // Data tersimpan pada rentang ini, dikelompokkan per tanggal.
        $tersimpan = \Illuminate\Support\Facades\DB::table('sheet_data as s')
            ->join('kendaraan as k', 'k.id', '=', 's.kendaraan_id')
            ->join('jenis_kendaraan as j', 'j.id', '=', 's.jenis_kendaraan_id')
            ->where('s.bidang_id', $bidang->id)
            ->whereBetween('s.tanggal', [$data['dari'], $data['sampai']])
            ->get(['s.tanggal', 's.kendaraan_id', 'j.nama as jenis', 'k.nomor_kendaraan as nomor', 's.jalan', 's.standby', 's.rusak', 's.perbaikan', 's.pm',
                's.total_jam_kerja as total', 's.availability_rate', 's.updated_at', 's.otomatis'])
            ->groupBy(fn ($r) => substr((string) $r->tanggal, 0, 10));

        // Kendaraan bidang ini beserta periode aktifnya (sama seperti tabel input per tanggal).
        $kendaraan = \Illuminate\Support\Facades\DB::table('kendaraan as k')
            ->join('jenis_kendaraan as j', 'j.id', '=', 'k.jenis_kendaraan_id')
            ->where('j.bidang_id', $bidang->id)
            ->get(['k.id', 'k.nomor_kendaraan as nomor', 'j.nama as jenis', 'k.aktif_sejak', 'k.nonaktif_sejak']);

        $rows = [];
        $terisi = 0;
        for ($t = \Carbon\Carbon::parse($data['dari']); $t->toDateString() <= $data['sampai']; $t->addDay()) {
            $tgl = $t->toDateString();
            $hariIni = [];
            foreach ($kendaraan as $k) {
                $mulai = $k->aktif_sejak ? substr((string) $k->aktif_sejak, 0, 10) : null;
                $akhir = $k->nonaktif_sejak ? substr((string) $k->nonaktif_sejak, 0, 10) : null;
                if ((! $mulai || $mulai <= $tgl) && (! $akhir || $akhir > $tgl)) {
                    $hariIni[$k->id] = [$tgl, $k->jenis, $k->nomor, null, null, null, null, null, null, null, null, 'belum'];
                }
            }
            // Kendaraan yang punya data di tanggal ini walau kini nonaktif / pindah bidang tetap ikut.
            foreach ($tersimpan->get($tgl, []) as $s) {
                $hariIni[$s->kendaraan_id] = [$tgl, $s->jenis, $s->nomor, (int) $s->jalan, (int) $s->standby, (int) $s->rusak, (int) $s->perbaikan,
                    (int) $s->pm, (int) $s->total, (float) $s->availability_rate,
                    ! $s->otomatis && $s->updated_at ? substr((string) $s->updated_at, 0, 19) : null, $s->otomatis ? 'otomatis' : 'diisi'];
                $terisi += $s->otomatis ? 0 : 1;
            }
            usort($hariIni, fn ($a, $b) => strcmp($a[1], $b[1]) ?: strnatcasecmp($a[2], $b[2]));
            array_push($rows, ...$hariIni);
        }

        return response()->json([
            'bidang' => $bidang->nama_tampil,
            'dari' => $data['dari'],
            'sampai' => $data['sampai'],
            'hari' => $hari,
            'terisi' => $terisi,
            'kolom' => ['tanggal', 'jenis', 'nomor', 'jalan', 'standby', 'rusak', 'perbaikan', 'pm', 'total', 'availability_rate', 'updated_at', 'status'],
            'rows' => $rows,
        ]);
    }

    public function simpan(Request $request, Bidang $bidang)
    {
        $this->authorizeBidang($request, $bidang);
        $data = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'rows' => ['required', 'array', 'min:1', 'max:2000'],
            'rows.*.kendaraan_id' => ['required', 'integer'],
            'rows.*.jalan' => ['sometimes', 'integer', 'between:0,24'],
            'rows.*.rusak' => ['sometimes', 'integer', 'between:0,24'],
            'rows.*.perbaikan' => ['sometimes', 'integer', 'between:0,24'],
            'rows.*.pm' => ['sometimes', 'integer', 'between:0,24'],
            'rows.*.asal' => ['sometimes', 'array'],
        ], ['tanggal.before_or_equal' => 'Tanggal tidak boleh melewati hari ini.']);

        $hasil = $this->svc->simpan($bidang, $data['tanggal'], $data['rows'], $request->user());

        return match ($hasil['status']) {
            'ok' => response()->json($hasil + [
                'message' => 'Tersimpan.',
                'grid' => $this->grid($bidang, $data['tanggal'], $request->user()),
            ]),
            'invalid' => response()->json($hasil + ['message' => 'Ada isian yang salah. Belum ada yang disimpan.'], 422),
            'conflict' => response()->json($hasil + ['message' => 'Data sudah diubah orang lain.'], 409),
            default => response()->json(['message' => 'Anda tidak punya akses mengubah data ini.'], 403),
        };
    }
}
