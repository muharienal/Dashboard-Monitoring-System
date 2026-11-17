<?php

namespace App\Http\Controllers;

use App\Models\Bidang;
use App\Models\User;
use App\Services\InputService;
use App\Support\RiwayatLogin;
use App\Support\RiwayatMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Riwayat perubahan data input (audit): siapa mengubah angka apa, dari berapa ke berapa, kapan. */
class RiwayatController extends Controller
{
    private const LABEL = ['jalan' => 'Jalan', 'standby' => 'Standby', 'rusak' => 'Rusak', 'perbaikan' => 'Perbaikan', 'pm' => 'PM'];

    public function index(Request $request)
    {
        $user = $request->user();
        if (in_array($request->query('tab'), ['master', 'login'], true) && ! $user->isAdmin()) {
            abort(403, 'Riwayat data master dan login hanya untuk admin.');
        }
        if ($request->query('tab') === 'master') {
            return $this->master($request);
        }
        if ($request->query('tab') === 'login') {
            return $this->login($request);
        }

        $f = [
            'q' => mb_substr($this->teks($request, 'q'), 0, 50),
            'bidang' => $this->teks($request, 'bidang'),
            'oleh' => ctype_digit($this->teks($request, 'oleh')) ? (int) $this->teks($request, 'oleh') : null,
            'tanggal' => $this->tgl($this->teks($request, 'tanggal')),
            'dari' => $this->tgl($this->teks($request, 'dari')),
            'sampai' => $this->tgl($this->teks($request, 'sampai')),
            'aksi' => in_array($this->teks($request, 'aksi'), ['buat', 'ubah'], true) ? $this->teks($request, 'aksi') : '',
        ];
        $bidangList = Bidang::ordered();
        // Petugas bidang: dikunci ke bidangnya sendiri, apa pun isi filter di URL.
        $batas = $user->bidangRiwayat();
        if ($batas !== null) {
            $bidangList = $bidangList->where('id', $batas)->values();
            $f['bidang'] = $bidangList->first()?->kode ?? '';
        }
        $bidang = $f['bidang'] !== '' ? $bidangList->firstWhere('kode', $f['bidang']) : null;
        if ($batas !== null && ! $bidang) {
            abort(403);
        }

        $q = DB::table('riwayat_input as r')->leftJoin('bidang as b', 'b.id', '=', 'r.bidang_id')
            ->select('r.*', 'b.nama_tampil as bidang')
            ->when($f['q'] !== '', fn ($w) => $w->where('r.nomor_kendaraan', 'like', '%'.addcslashes($f['q'], '%_\\').'%'))
            ->when($bidang, fn ($w) => $w->where('r.bidang_id', $bidang->id))
            ->when($f['oleh'], fn ($w) => $w->where('r.user_id', $f['oleh']))
            ->when($f['tanggal'], fn ($w) => $w->where('r.tanggal', $f['tanggal']))
            ->when($f['dari'], fn ($w) => $w->where('r.created_at', '>=', $f['dari'].' 00:00:00'))
            ->when($f['sampai'], fn ($w) => $w->where('r.created_at', '<=', $f['sampai'].' 23:59:59'))
            ->when($f['aksi'], fn ($w) => $w->where('r.aksi', $f['aksi']))
            ->orderByDesc('r.created_at')->orderByDesc('r.id');

        if ($request->query('format') === 'xlsx') {
            $rows = $q->limit(20000)->get();
            $aoa = [['Riwayat perubahan data input'], ['Waktu', 'Oleh', 'Bidang', 'Tanggal data', 'Nomor', 'Aksi', 'Perubahan']];
            foreach ($rows as $r) {
                $aoa[] = [$r->created_at, $r->user_nama, $r->bidang, $r->tanggal, $r->nomor_kendaraan,
                    $r->aksi === 'buat' ? 'Data baru' : 'Diubah', collect($this->perubahan($r))->map(fn ($p) => $p['teks'])->join('; ')];
            }

            return response()->json(['aoa' => $aoa, 'jumlah' => $rows->count()]);
        }

        $per = in_array((int) $request->query('per'), [25, 50, 100], true) ? (int) $request->query('per') : 50;
        $riwayat = $q->paginate($per)->withQueryString();
        $riwayat->getCollection()->transform(function ($r) {
            $r->ubah = $this->perubahan($r);

            return $r;
        });

        $pengubah = User::query()->whereIn('id', DB::table('riwayat_input')->whereNotNull('user_id')
            ->when($batas !== null, fn ($w) => $w->where('bidang_id', $batas))->distinct()->pluck('user_id'))
            ->orderBy('name')->get(['id', 'name']);

        return view('riwayat.index', compact('riwayat', 'f', 'bidangList', 'pengubah', 'per', 'batas'));
    }

    /** Tab "Login": siapa masuk/keluar kapan dari perangkat apa, termasuk percobaan gagal. */
    private function login(Request $request)
    {
        $f = [
            'q' => mb_substr($this->teks($request, 'q'), 0, 50),
            'hasil' => array_key_exists($this->teks($request, 'hasil'), RiwayatLogin::HASIL) ? $this->teks($request, 'hasil') : '',
            'dari' => $this->tgl($this->teks($request, 'dari')),
            'sampai' => $this->tgl($this->teks($request, 'sampai')),
        ];
        $like = '%'.addcslashes($f['q'], '%_\\').'%';
        $q = DB::table('riwayat_login as r')
            ->when($f['q'] !== '', fn ($w) => $w->where(fn ($x) => $x->where('r.user_nama', 'like', $like)->orWhere('r.isian', 'like', $like)->orWhere('r.ip', 'like', $like)))
            ->when($f['hasil'], fn ($w) => $w->where('r.hasil', $f['hasil']))
            ->when($f['dari'], fn ($w) => $w->where('r.created_at', '>=', $f['dari'].' 00:00:00'))
            ->when($f['sampai'], fn ($w) => $w->where('r.created_at', '<=', $f['sampai'].' 23:59:59'))
            ->orderByDesc('r.created_at')->orderByDesc('r.id');

        if ($request->query('format') === 'xlsx') {
            $rows = $q->limit(20000)->get();
            $aoa = [['Riwayat login'], ['Waktu', 'Akun', 'Nama pengguna diketik', 'Hasil', 'Perangkat', 'IP']];
            foreach ($rows as $r) {
                $aoa[] = [$r->created_at, $r->user_nama, $r->isian, RiwayatLogin::HASIL[$r->hasil] ?? $r->hasil, $r->perangkat, $r->ip];
            }

            return response()->json(['aoa' => $aoa, 'jumlah' => $rows->count()]);
        }
        $per = in_array((int) $request->query('per'), [25, 50, 100], true) ? (int) $request->query('per') : 50;
        $riwayat = $q->paginate($per)->withQueryString();
        $gagal24 = DB::table('riwayat_login')->whereIn('hasil', ['gagal', 'terkunci'])->where('created_at', '>=', now()->subDay())->count();

        return view('riwayat.login', compact('riwayat', 'f', 'per', 'gagal24'));
    }

    /** Tab "Data master": perubahan kendaraan, jenis, bidang, dan akun. */
    private function master(Request $request)
    {
        $f = [
            'q' => mb_substr($this->teks($request, 'q'), 0, 50),
            'objek' => array_key_exists($this->teks($request, 'objek'), RiwayatMaster::OBJEK) ? $this->teks($request, 'objek') : '',
            'aksi' => array_key_exists($this->teks($request, 'aksi'), RiwayatMaster::AKSI) ? $this->teks($request, 'aksi') : '',
            'oleh' => ctype_digit($this->teks($request, 'oleh')) ? (int) $this->teks($request, 'oleh') : null,
            'dari' => $this->tgl($this->teks($request, 'dari')),
            'sampai' => $this->tgl($this->teks($request, 'sampai')),
        ];
        $q = DB::table('riwayat_master as r')
            ->when($f['q'] !== '', fn ($w) => $w->where('r.objek_nama', 'like', '%'.addcslashes($f['q'], '%_\\').'%'))
            ->when($f['objek'], fn ($w) => $w->where('r.objek', $f['objek']))
            ->when($f['aksi'], fn ($w) => $w->where('r.aksi', $f['aksi']))
            ->when($f['oleh'], fn ($w) => $w->where('r.user_id', $f['oleh']))
            ->when($f['dari'], fn ($w) => $w->where('r.created_at', '>=', $f['dari'].' 00:00:00'))
            ->when($f['sampai'], fn ($w) => $w->where('r.created_at', '<=', $f['sampai'].' 23:59:59'))
            ->orderByDesc('r.created_at')->orderByDesc('r.id');

        $teks = fn ($r) => collect($r->perubahan ? json_decode($r->perubahan, true) : [])
            ->map(fn ($p) => $p['label'].': '.($p['dari'] ?? '–').' → '.($p['ke'] ?? '–'))
            ->when($r->catatan, fn ($c) => $c->push($r->catatan))->join('; ');

        if ($request->query('format') === 'xlsx') {
            $rows = $q->limit(20000)->get();
            $aoa = [['Riwayat perubahan data master'], ['Waktu', 'Oleh', 'Data', 'Nama', 'Aksi', 'Perubahan']];
            foreach ($rows as $r) {
                $aoa[] = [$r->created_at, $r->user_nama, RiwayatMaster::OBJEK[$r->objek] ?? $r->objek, $r->objek_nama,
                    RiwayatMaster::AKSI[$r->aksi] ?? $r->aksi, $teks($r)];
            }

            return response()->json(['aoa' => $aoa, 'jumlah' => $rows->count()]);
        }

        $per = in_array((int) $request->query('per'), [25, 50, 100], true) ? (int) $request->query('per') : 50;
        $riwayat = $q->paginate($per)->withQueryString();
        $riwayat->getCollection()->transform(function ($r) {
            $r->ubah = $r->perubahan ? json_decode($r->perubahan, true) : [];

            return $r;
        });
        $pengubah = User::query()->whereIn('id', DB::table('riwayat_master')->whereNotNull('user_id')->distinct()->pluck('user_id'))
            ->orderBy('name')->get(['id', 'name']);

        $kendaraanAda = DB::table('kendaraan')->whereIn('id', $riwayat->getCollection()->where('objek', 'kendaraan')->pluck('objek_id')->filter())
            ->pluck('id')->flip()->all();

        return view('riwayat.master', compact('riwayat', 'f', 'pengubah', 'per', 'kendaraanAda'));
    }

    /** @return array<int, array{kolom:string, label:string, dari:?int, ke:int, teks:string}> */
    private function perubahan(object $r): array
    {
        $sebelum = $r->sebelum ? json_decode($r->sebelum, true) : null;
        $sesudah = json_decode($r->sesudah, true) ?: [];
        $hasil = [];
        foreach (InputService::KOLOM_RIWAYAT as $k) {
            $ke = (int) ($sesudah[$k] ?? 0);
            $dari = $sebelum === null ? null : (int) ($sebelum[$k] ?? 0);
            // Data baru: tampilkan semua kolom bernilai; ubah: hanya kolom yang berubah.
            if ($sebelum === null ? ($ke === 0 && $k !== 'standby') : $dari === $ke) {
                continue;
            }
            $label = self::LABEL[$k];
            $hasil[] = ['kolom' => $k, 'label' => $label, 'dari' => $dari, 'ke' => $ke,
                'teks' => $dari === null ? "{$label} {$ke}" : "{$label} {$dari} → {$ke}"];
        }

        return $hasil;
    }

    private function tgl(string $v): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4)) ? $v : null;
    }
}
