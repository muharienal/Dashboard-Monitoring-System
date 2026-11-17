<?php

namespace App\Http\Controllers;

use App\Models\Bidang;
use App\Models\JenisKendaraan;
use App\Models\Kendaraan;
use App\Support\IsiOtomatis;
use App\Support\RiwayatMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class KendaraanController extends Controller
{
    public function index(Request $request)
    {
        $q = Kendaraan::query()
            ->join('jenis_kendaraan as j', 'j.id', '=', 'kendaraan.jenis_kendaraan_id')
            ->join('bidang as b', 'b.id', '=', 'j.bidang_id')
            ->select('kendaraan.*', 'j.nama as jenis', 'j.bidang_id', 'b.nama_tampil as bidang', 'b.urutan')
            ->withCount(['sheetData' => fn ($q) => $q->where('otomatis', false)])
            ->withMax(['sheetData' => fn ($q) => $q->where('otomatis', false)], 'tanggal');

        if ($s = $this->teks($request, 'q')) {
            $q->where(fn ($w) => $w->where('kendaraan.nomor_kendaraan', 'like', "%{$s}%")
                ->orWhere('j.nama', 'like', "%{$s}%")->orWhere('kendaraan.keterangan', 'like', "%{$s}%"));
        }
        if ($b = $this->teks($request, 'bidang')) {
            $q->where('b.kode', $b);
        }
        if ($j = $this->teks($request, 'jenis')) {
            $q->where('j.nama', $j);
        }
        match ($this->teks($request, 'status')) {
            'aktif' => $q->whereNull('kendaraan.nonaktif_sejak'),
            'nonaktif' => $q->whereNotNull('kendaraan.nonaktif_sejak'),
            default => null,
        };
        match ($this->teks($request, 'riwayat')) {
            'ada' => $q->has('sheetData'),
            'belum' => $q->doesntHave('sheetData'),
            default => null,
        };

        $sorts = [
            'nomor' => ['kendaraan.nomor_kendaraan'],
            'jenis' => ['j.nama', 'kendaraan.nomor_kendaraan'],
            'bidang' => ['b.urutan', 'j.nama', 'kendaraan.nomor_kendaraan'],
            'riwayat' => ['sheet_data_count'],
            'terakhir' => ['sheet_data_max_tanggal'],
            'mulai' => ['kendaraan.aktif_sejak'],
        ];
        $sort = array_key_exists($this->teks($request, 'sort'), $sorts) ? $this->teks($request, 'sort') : 'bidang';
        $dir = $this->teks($request, 'dir') === 'desc' ? 'desc' : 'asc';
        foreach ($sorts[$sort] as $col) {
            $q->orderBy($col, $dir);
        }
        $per = in_array((int) $this->teks($request, 'per'), [25, 50, 100], true) ? (int) $this->teks($request, 'per') : 25;

        $bidangList = Bidang::ordered()->load(['jenisKendaraan' => fn ($q) => $q->withCount('kendaraan')]);

        return view('master.kendaraan', [
            'kendaraan' => $q->paginate($per)->withQueryString(),
            'bidangList' => $bidangList,
            'jenisList' => $bidangList->flatMap->jenisKendaraan->pluck('nama')->unique()->sort()->values(),
            'sort' => $sort, 'dir' => $dir, 'per' => $per,
            'f' => collect(['q', 'bidang', 'jenis', 'status', 'riwayat'])->mapWithKeys(fn ($k) => [$k => $this->teks($request, $k)])->all(),
        ]);
    }

    private function rules(?Kendaraan $k = null): array
    {
        return [
            'jenis_kendaraan_id' => ['required', 'integer', 'exists:jenis_kendaraan,id'],
            'nomor_kendaraan' => ['required', 'string', 'max:50',
                Rule::unique('kendaraan', 'nomor_kendaraan')
                    ->where('jenis_kendaraan_id', (int) $this->teks(request(), 'jenis_kendaraan_id'))
                    ->ignore($k?->id)],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'aktif_sejak' => ['required', 'date_format:Y-m-d'],
        ];
    }

    private function messages(): array
    {
        return ['nomor_kendaraan.unique' => 'Nomor ini sudah ada di jenis tersebut.'];
    }

    public function store(Request $request)
    {
        $request->merge(['nomor_kendaraan' => Kendaraan::normalizeNomor($this->teks($request, 'nomor_kendaraan'))]);
        $data = $request->validate($this->rules(), $this->messages());
        $k = Kendaraan::create($data + ['is_active' => true, 'nonaktif_sejak' => null]);
        RiwayatMaster::catat('kendaraan', $k->id, $k->nomor_kendaraan, 'tambah', null, RiwayatMaster::kendaraan($k));
        IsiOtomatis::rapikan($k);
        Cache::forget('dms:data-version');

        return back()->with('success', "{$data['nomor_kendaraan']} ditambahkan.");
    }

    public function update(Request $request, Kendaraan $kendaraan)
    {
        $request->merge(['nomor_kendaraan' => Kendaraan::normalizeNomor($this->teks($request, 'nomor_kendaraan'))]);
        $data = $request->validate($this->rules($kendaraan), $this->messages());
        if ($kendaraan->nonaktif_sejak && $data['aktif_sejak'] >= $kendaraan->nonaktif_sejak->toDateString()) {
            return back()->with('error', 'Tanggal mulai beroperasi harus sebelum tanggal nonaktif.');
        }
        $sebelum = RiwayatMaster::kendaraan($kendaraan);
        $kendaraan->update($data);
        RiwayatMaster::catat('kendaraan', $kendaraan->id, $kendaraan->nomor_kendaraan, 'ubah', $sebelum, RiwayatMaster::kendaraan($kendaraan->fresh()));
        IsiOtomatis::rapikan($kendaraan);
        Cache::forget('dms:data-version');

        return back()->with('success', "{$kendaraan->nomor_kendaraan} disimpan.");
    }

    /** Nonaktifkan (berlaku mulai tanggal tertentu) atau aktifkan kembali. */
    public function toggle(Request $request, Kendaraan $kendaraan)
    {
        if ($this->teks($request, 'aksi') === 'aktif') {
            $sebelum = RiwayatMaster::kendaraan($kendaraan);
            $kendaraan->update(['is_active' => true, 'nonaktif_sejak' => null]);
            RiwayatMaster::catat('kendaraan', $kendaraan->id, $kendaraan->nomor_kendaraan, 'aktif', $sebelum, RiwayatMaster::kendaraan($kendaraan->fresh()));
            IsiOtomatis::rapikan($kendaraan);
            Cache::forget('dms:data-version');

            return back()->with('success', "{$kendaraan->nomor_kendaraan} diaktifkan kembali.");
        }

        $data = $request->validate([
            'nonaktif_sejak' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ], ['nonaktif_sejak.before_or_equal' => 'Tanggal nonaktif tidak boleh melewati hari ini.'], ['nonaktif_sejak' => 'tanggal nonaktif']);

        if ($kendaraan->aktif_sejak && $data['nonaktif_sejak'] <= $kendaraan->aktif_sejak->toDateString()) {
            return back()->with('error', 'Tanggal nonaktif harus setelah tanggal mulai beroperasi ('.$kendaraan->aktif_sejak->translatedFormat('j M Y').').');
        }
        $sebelum = RiwayatMaster::kendaraan($kendaraan);
        $kendaraan->update([
            'is_active' => false,
            'nonaktif_sejak' => $data['nonaktif_sejak'],
            'keterangan' => ($data['keterangan'] ?? null) ?: $kendaraan->keterangan,
        ]);
        RiwayatMaster::catat('kendaraan', $kendaraan->id, $kendaraan->nomor_kendaraan, 'nonaktif', $sebelum, RiwayatMaster::kendaraan($kendaraan->fresh()));
        IsiOtomatis::rapikan($kendaraan); // data otomatis mulai tanggal nonaktif dihapus
        Cache::forget('dms:data-version');

        $sesudah = $kendaraan->sheetData()->where('otomatis', false)->where('tanggal', '>=', $data['nonaktif_sejak'])->count();
        $pesan = "{$kendaraan->nomor_kendaraan} nonaktif mulai ".Carbon::parse($data['nonaktif_sejak'])->translatedFormat('j F Y').'. Riwayatnya tetap tersimpan.';
        if ($sesudah) {
            $pesan .= " Ada {$sesudah} hari data setelah tanggal itu yang tetap disimpan.";
        }

        return back()->with('success', $pesan);
    }

    public function destroy(Kendaraan $kendaraan)
    {
        // Baris otomatis (Standby 24 jam dari sistem) bukan riwayat isian, jadi tidak menghalangi hapus.
        if ($kendaraan->sheetData()->where('otomatis', false)->exists()) {
            return back()->with('error', "{$kendaraan->nomor_kendaraan} punya riwayat, tidak bisa dihapus. Nonaktifkan saja.");
        }
        $sebelum = RiwayatMaster::kendaraan($kendaraan);
        \Illuminate\Support\Facades\DB::transaction(function () use ($kendaraan) {
            $kendaraan->sheetData()->where('otomatis', true)->delete();
            $kendaraan->delete();
        });
        RiwayatMaster::catat('kendaraan', $kendaraan->id, $kendaraan->nomor_kendaraan, 'hapus', $sebelum);
        Cache::forget('dms:data-version');

        return back()->with('success', "{$kendaraan->nomor_kendaraan} dihapus.");
    }

    // ----- Jenis kendaraan -----
    public function storeJenis(Request $request)
    {
        $request->merge(['nama' => mb_strtoupper($this->teks($request, 'nama'))]);
        $data = $request->validate([
            'bidang_id' => ['required', 'exists:bidang,id'],
            'nama' => ['required', 'string', 'max:100', Rule::unique('jenis_kendaraan')->where('bidang_id', (int) $this->teks($request, 'bidang_id'))],
        ], ['nama.unique' => 'Jenis ini sudah ada.']);
        $j = JenisKendaraan::create($data);
        RiwayatMaster::catat('jenis', $j->id, $j->nama, 'tambah', null, RiwayatMaster::jenis($j));

        return back()->with('success', "{$data['nama']} ditambahkan.");
    }

    public function updateJenis(Request $request, JenisKendaraan $jenis)
    {
        $request->merge(['nama' => mb_strtoupper($this->teks($request, 'nama'))]);
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique('jenis_kendaraan')->where('bidang_id', $jenis->bidang_id)->ignore($jenis->id)],
        ], ['nama.unique' => 'Jenis ini sudah ada.']);
        $sebelum = RiwayatMaster::jenis($jenis);
        $jenis->update($data);
        RiwayatMaster::catat('jenis', $jenis->id, $jenis->nama, 'ubah', $sebelum, RiwayatMaster::jenis($jenis));

        return back()->with('success', 'Jenis disimpan.');
    }

    public function destroyJenis(JenisKendaraan $jenis)
    {
        if ($jenis->kendaraan()->exists()) {
            return back()->with('error', "{$jenis->nama} masih punya kendaraan, tidak bisa dihapus.");
        }
        $sebelum = RiwayatMaster::jenis($jenis);
        $jenis->delete();
        RiwayatMaster::catat('jenis', $jenis->id, $jenis->nama, 'hapus', $sebelum);

        return back()->with('success', "{$jenis->nama} dihapus.");
    }

    // ----- Pengaturan bidang -----
    public function updateBidang(Request $request, Bidang $bidang)
    {
        $data = $request->validate([
            'nama_tampil' => ['required', 'string', 'max:100'],
            'urutan' => ['required', 'integer', 'min:0', 'max:999'],
            'input_semua_kolom' => ['nullable', 'boolean'],
        ]);
        $data['input_semua_kolom'] = $request->boolean('input_semua_kolom');
        $sebelum = RiwayatMaster::bidang($bidang);
        $bidang->update($data);
        RiwayatMaster::catat('bidang', $bidang->id, $bidang->nama, 'ubah', $sebelum, RiwayatMaster::bidang($bidang));

        return back()->with('success', "{$bidang->nama_tampil} disimpan.");
    }
}
