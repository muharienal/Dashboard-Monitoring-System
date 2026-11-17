<?php

namespace App\Services;

use App\Models\Bidang;
use App\Support\Filter;
use App\Support\JamKerja;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Pengganti seluruh workbook Tableau. Setiap method = satu worksheet, dihitung langsung
 * dari tabel sheet_data (data inputan user) sehingga selalu realtime.
 *
 * Padanan rumus Tableau:
 *   AR JALAN + STANDBY          = SUM(jalan) + SUM(standby)
 *   AR PERBAIKAN + RUSAK + PM   = SUM(perbaikan)+SUM(rusak)+SUM(pm)+SUM(jalan)+SUM(standby)   (= total jam)
 *   AVAILABILITY RATE FIX       = AR JALAN + STANDBY / AR PERBAIKAN + RUSAK + PM * 100
 *   Utilitas (rata-rata unit)   = SUM(utilitas_x) / COUNTD(tanggal)
 *   Maintenance (warna)         = SUM(perbaikan) / COUNTD(tanggal)
 *   Utilitas Unit               = SUM(amount) (= jumlah unit-hari), SUM(utilitas_x)
 *   Pie / Detail Report         = SUM(pivot_values) per pivot_names (JALAN, STANDBY, RUSAK, PERBAIKAN, PM)
 */
class DashboardService
{
    private function base(Filter $f, bool $withBidang = true): Builder
    {
        return $f->apply(DB::table('sheet_data as s'), 's', $withBidang);
    }

    private function withJenis(Builder $q, Filter $f): Builder
    {
        $q->join('jenis_kendaraan as j', 'j.id', '=', 's.jenis_kendaraan_id');
        if ($f->jenis) {
            $q->where('j.nama', $f->jenis);
        }

        return $q;
    }

    /** Ekspresi SQL "YYYY-MM" (MySQL/MariaDB, dan SQLite untuk testing). */
    private static function bulanExpr(): string
    {
        return DB::getDriverName() === 'sqlite' ? "strftime('%Y-%m', s.tanggal)" : "DATE_FORMAT(s.tanggal, '%Y-%m')";
    }

    private static function ar(float|int $jalanStandby, float|int $total): ?float
    {
        return $total > 0 ? round($jalanStandby / $total * 100, 2) : null;
    }

    /** Versi data untuk realtime polling: berubah setiap ada insert/update. */
    public function version(): string
    {
        return Cache::remember('dms:data-version', 3, function () {
            $r = DB::table('sheet_data')->selectRaw('MAX(updated_at) as u, MAX(id) as i, COUNT(*) as c')->first();
            $m = DB::table('kendaraan')->selectRaw('MAX(updated_at) as u, COUNT(*) as c')->first();

            return substr(sha1(json_encode([$r, $m])), 0, 16);
        });
    }

    /** Pie AVAILABILITY: total jam per status + AR. */
    public function availability(Filter $f): array
    {
        $q = $this->withJenis($this->base($f), $f);
        $r = $q->selectRaw('COALESCE(SUM(s.jalan),0) jalan, COALESCE(SUM(s.standby),0) standby, COALESCE(SUM(s.rusak),0) rusak,
                COALESCE(SUM(s.perbaikan),0) perbaikan, COALESCE(SUM(s.pm),0) pm, COUNT(*) unit_hari,
                COUNT(DISTINCT s.kendaraan_id) unit, COUNT(DISTINCT s.tanggal) hari, COALESCE(SUM(s.utilitas_jalan),0) uj')->first();

        return $this->formatAvailability((array) $r);
    }

    private function formatAvailability(array $r): array
    {
        $jam = [];
        foreach (JamKerja::STATUS as $st) {
            $jam[$st] = (int) $r[strtolower($st)];
        }
        $total = array_sum($jam);

        return [
            'jam' => $jam,
            'total_jam' => $total,
            'availability_rate' => self::ar($jam['JALAN'] + $jam['STANDBY'], $total),
            'unit' => (int) ($r['unit'] ?? 0),
            'unit_hari' => (int) ($r['unit_hari'] ?? 0),
            'hari' => (int) ($r['hari'] ?? 0),
            // Utilisasi = porsi unit-hari berstatus JALAN (persen)
            'utilisasi' => ! empty($r['unit_hari']) ? round(($r['uj'] ?? 0) / $r['unit_hari'] * 100, 2) : null,
            'avg_jalan' => ! empty($r['hari']) ? round(($r['uj'] ?? 0) / $r['hari'], 2) : null,
        ];
    }

    /** Availability Assets: satu donut per jenis kendaraan. */
    public function availabilityPerJenis(Filter $f): array
    {
        $rows = $this->withJenis($this->base($f), $f)
            ->selectRaw('j.nama jenis, SUM(s.jalan) jalan, SUM(s.standby) standby, SUM(s.rusak) rusak, SUM(s.perbaikan) perbaikan,
                SUM(s.pm) pm, COUNT(*) unit_hari, COUNT(DISTINCT s.kendaraan_id) unit, COUNT(DISTINCT s.tanggal) hari, SUM(s.utilitas_jalan) uj')
            ->groupBy('j.nama')->orderBy('j.nama')->get();

        return $rows->map(fn ($r) => ['jenis' => $r->jenis] + $this->formatAvailability((array) $r))->all();
    }

    /** UTILITAS (bar per bulan) & TREND LINES: rata-rata unit per hari per status. */
    public function utilitasPeriodik(Filter $f, string $granularity): array
    {
        $q = $this->withJenis($this->base($f), $f);
        if ($granularity === 'hari') {
            $q->selectRaw('s.tanggal periode');
            $q->groupBy('s.tanggal')->orderBy('s.tanggal');
        } else {
            $q->selectRaw(self::bulanExpr().' periode');
            $q->groupBy('periode')->orderBy('periode');
        }
        $rows = $q->selectRaw('SUM(s.utilitas_jalan) uj, SUM(s.utilitas_standby) us, SUM(s.utilitas_rusak) ur,
                COUNT(DISTINCT s.tanggal) hari, COUNT(*) unit_hari')->get();

        return $rows->map(fn ($r) => [
            'periode' => (string) $r->periode,
            'jalan' => round($r->uj / max(1, $r->hari), 2),
            'standby' => round($r->us / max(1, $r->hari), 2),
            'rusak' => round($r->ur / max(1, $r->hari), 2),
            'hari' => (int) $r->hari,
            'unit_hari' => (int) $r->unit_hari,
        ])->all();
    }

    /** Utilisasi Assets: per jenis kendaraan -> jumlah (SUM) dan rata-rata per hari (SUM/COUNTD tanggal). */
    public function utilisasiPerJenis(Filter $f): array
    {
        $rows = $this->withJenis($this->base($f), $f)
            ->selectRaw('j.nama jenis, COUNT(*) amount, SUM(s.utilitas_jalan) uj, SUM(s.utilitas_standby) us,
                SUM(s.utilitas_rusak) ur, COUNT(DISTINCT s.tanggal) hari, COUNT(DISTINCT s.kendaraan_id) unit')
            ->groupBy('j.nama')->orderBy('j.nama')->get();

        return $rows->map(function ($r) {
            $h = max(1, (int) $r->hari);

            return [
                'jenis' => $r->jenis,
                'unit' => (int) $r->unit,
                'hari' => (int) $r->hari,
                'amount' => (int) $r->amount,
                'jalan' => (int) $r->uj, 'standby' => (int) $r->us, 'rusak' => (int) $r->ur,
                'avg_jalan' => round($r->uj / $h, 2),
                'avg_standby' => round($r->us / $h, 2),
                'avg_rusak' => round($r->ur / $h, 2),
                'avg_unit' => round($r->amount / $h, 2),
                'persen_jalan' => $r->amount > 0 ? round($r->uj / $r->amount * 100, 1) : 0,
            ];
        })->all();
    }

    /** MAINTENANCE: total jam perbaikan per kendaraan (warna = rata-rata jam perbaikan per hari). */
    public function maintenance(Filter $f, ?int $limit = null, bool $hanyaAda = true): array
    {
        $q = $this->withJenis($this->base($f), $f)
            ->join('kendaraan as k', 'k.id', '=', 's.kendaraan_id')
            ->selectRaw('s.kendaraan_id id, k.nomor_kendaraan nomor, j.nama jenis, SUM(s.perbaikan) perbaikan, SUM(s.pm) pm, SUM(s.rusak) rusak,
                COUNT(DISTINCT s.tanggal) hari, SUM(CASE WHEN s.perbaikan > 0 THEN 1 ELSE 0 END) hari_perbaikan')
            ->groupBy('s.kendaraan_id', 'k.nomor_kendaraan', 'j.nama')
            ->orderByDesc('perbaikan')->orderBy('k.nomor_kendaraan');
        if ($hanyaAda) {
            $q->havingRaw('SUM(s.perbaikan) > 0');
        }
        if ($limit) {
            $q->limit($limit);
        }

        return $q->get()->map(fn ($r) => [
            'id' => (int) $r->id,
            'nomor' => $r->nomor,
            'jenis' => $r->jenis,
            'perbaikan' => (int) $r->perbaikan,
            'pm' => (int) $r->pm,
            'rusak' => (int) $r->rusak,
            'hari' => (int) $r->hari,
            'hari_perbaikan' => (int) $r->hari_perbaikan,
            'rata_rata' => round($r->perbaikan / max(1, $r->hari), 2),
        ])->all();
    }

    /**
     * Detail Report (crosstab): baris = bidang / jenis / nomor / status, kolom = tanggal (bila 1 bulan dipilih)
     * atau bulan (bila 1 tahun). Nilai = SUM(jam).
     */
    public function detailReport(Filter $f): array
    {
        $perHari = $f->tahun && $f->bulan;
        $q = $this->withJenis($this->base($f), $f)
            ->join('kendaraan as k', 'k.id', '=', 's.kendaraan_id')
            ->join('bidang as b', 'b.id', '=', 's.bidang_id');

        $driver = DB::getDriverName();
        $kol = $perHari
            ? ($driver === 'sqlite' ? "CAST(strftime('%d', s.tanggal) AS INTEGER)" : 'DAY(s.tanggal)')
            : self::bulanExpr();

        $rows = $q->selectRaw("b.nama_tampil bidang, b.urutan, j.nama jenis, k.id kid, k.nomor_kendaraan nomor, {$kol} kol,
                SUM(s.jalan) jalan, SUM(s.standby) standby, SUM(s.rusak) rusak, SUM(s.perbaikan) perbaikan, SUM(s.pm) pm")
            ->groupBy('b.nama_tampil', 'b.urutan', 'j.nama', 'k.id', 'k.nomor_kendaraan', 'kol')
            ->orderBy('b.urutan')->orderBy('j.nama')->orderBy('k.nomor_kendaraan')->orderBy('kol')
            ->get();

        $kolom = [];
        if ($perHari) {
            $kolom = range(1, cal_days_in_month(CAL_GREGORIAN, $f->bulan, $f->tahun));
            if ($f->hari) {
                $kolom = [$f->hari];
            }
        }
        $unit = [];
        foreach ($rows as $r) {
            $key = "{$r->bidang}|{$r->jenis}|{$r->nomor}";
            $unit[$key] ??= ['id' => (int) $r->kid, 'bidang' => $r->bidang, 'jenis' => $r->jenis, 'nomor' => $r->nomor, 'nilai' => []];
            foreach (JamKerja::STATUS as $st) {
                $unit[$key]['nilai'][$st][(string) $r->kol] = (int) $r->{strtolower($st)};
            }
            if (! $perHari && ! in_array((string) $r->kol, $kolom, true)) {
                $kolom[] = (string) $r->kol;
            }
        }
        if (! $perHari) {
            sort($kolom);
        }

        return ['mode' => $perHari ? 'hari' : 'bulan', 'kolom' => array_map('strval', $kolom), 'unit' => array_values($unit)];
    }

    /** General Report: rekap per bidang & jenis. */
    public function rekap(Filter $f): array
    {
        $rows = $this->withJenis($this->base($f), $f)
            ->join('bidang as b', 'b.id', '=', 's.bidang_id')
            ->selectRaw('b.nama_tampil bidang, b.urutan, j.nama jenis, COUNT(DISTINCT s.kendaraan_id) unit, COUNT(*) unit_hari,
                COUNT(DISTINCT s.tanggal) hari, SUM(s.jalan) jalan, SUM(s.standby) standby, SUM(s.rusak) rusak,
                SUM(s.perbaikan) perbaikan, SUM(s.pm) pm, SUM(s.utilitas_jalan) uj, SUM(s.utilitas_standby) us, SUM(s.utilitas_rusak) ur')
            ->groupBy('b.nama_tampil', 'b.urutan', 'j.nama')->orderBy('b.urutan')->orderBy('j.nama')->get();

        return $rows->map(function ($r) {
            $total = $r->jalan + $r->standby + $r->rusak + $r->perbaikan + $r->pm;
            $h = max(1, (int) $r->hari);

            return [
                'bidang' => $r->bidang, 'jenis' => $r->jenis, 'unit' => (int) $r->unit, 'unit_hari' => (int) $r->unit_hari,
                'hari' => (int) $r->hari,
                'jalan' => (int) $r->jalan, 'standby' => (int) $r->standby, 'rusak' => (int) $r->rusak,
                'perbaikan' => (int) $r->perbaikan, 'pm' => (int) $r->pm, 'total_jam' => (int) $total,
                'availability_rate' => self::ar($r->jalan + $r->standby, $total),
                'avg_jalan' => round($r->uj / $h, 2), 'avg_standby' => round($r->us / $h, 2), 'avg_rusak' => round($r->ur / $h, 2),
                'utilisasi' => $r->unit_hari > 0 ? round($r->uj / $r->unit_hari * 100, 2) : null,
            ];
        })->all();
    }

    /** Beranda: KPI per bidang + kelengkapan input tanggal tertentu. */
    public function ringkasanBidang(Filter $f, string $tanggalCek): array
    {
        $agg = $this->base($f, false)
            ->selectRaw('s.bidang_id, SUM(s.jalan)+SUM(s.standby) js, SUM(s.total_jam_kerja) total, SUM(s.perbaikan) perbaikan,
                SUM(s.utilitas_jalan) uj, COUNT(*) unit_hari, COUNT(DISTINCT s.tanggal) hari, MAX(s.updated_at) terakhir')
            ->groupBy('s.bidang_id')->get()->keyBy('bidang_id');

        $terisi = DB::table('sheet_data')->where('tanggal', $tanggalCek)
            ->selectRaw('bidang_id, COUNT(*) c')->groupBy('bidang_id')->pluck('c', 'bidang_id');

        $aktif = DB::table('kendaraan as k')->join('jenis_kendaraan as j', 'j.id', '=', 'k.jenis_kendaraan_id')
            ->where('k.is_active', true)->selectRaw('j.bidang_id, COUNT(*) c')->groupBy('j.bidang_id')->pluck('c', 'bidang_id');

        $terakhirInput = DB::table('sheet_data')->selectRaw('bidang_id, MAX(tanggal) t, MAX(updated_at) u')
            ->groupBy('bidang_id')->get()->keyBy('bidang_id');

        return Bidang::ordered()->map(function (Bidang $b) use ($agg, $terisi, $aktif, $terakhirInput) {
            $a = $agg[$b->id] ?? null;
            $hari = max(1, (int) ($a->hari ?? 0));

            return [
                'kode' => $b->kode,
                'nama' => $b->nama_tampil,
                'availability_rate' => $a ? self::ar($a->js, $a->total) : null,
                'avg_jalan' => $a ? round($a->uj / $hari, 1) : null,
                'utilisasi' => ($a && $a->unit_hari > 0) ? round($a->uj / $a->unit_hari * 100, 1) : null,
                'perbaikan' => (int) ($a->perbaikan ?? 0),
                'unit_aktif' => (int) ($aktif[$b->id] ?? 0),
                'terisi' => (int) ($terisi[$b->id] ?? 0),
                'tanggal_terakhir' => $terakhirInput[$b->id]->t ?? null,
                'diperbarui' => $terakhirInput[$b->id]->u ?? null,
            ];
        })->all();
    }

    /**
     * Isian "kosong" satu bulan per bidang: tanggal yang punya data tetapi tidak ada satu unit pun
     * yang tercatat Jalan atau Rusak — tanda kolom bagian Bidang belum diisi.
     *
     * @return array<string, array{tanggal_data:int, tanggal_kosong:int, contoh:string[]}>
     */
    public function isianKosongBulan(int $tahun, int $bulan): array
    {
        $per = DB::table('sheet_data as s')
            ->whereBetween('s.tanggal', [sprintf('%04d-%02d-01', $tahun, $bulan), \Carbon\CarbonImmutable::create($tahun, $bulan, 1)->endOfMonth()->toDateString()])
            ->selectRaw('s.bidang_id, s.tanggal, COUNT(*) n, SUM(CASE WHEN s.jalan = 0 AND s.rusak = 0 THEN 1 ELSE 0 END) k')
            ->groupBy('s.bidang_id', 's.tanggal')->orderBy('s.tanggal')->get()->groupBy('bidang_id');

        return Bidang::ordered()->mapWithKeys(function (Bidang $b) use ($per) {
            $rows = $per->get($b->id, collect());
            $kosong = $rows->filter(fn ($r) => (int) $r->n > 0 && (int) $r->k === (int) $r->n);

            return [$b->kode => [
                'tanggal_data' => $rows->count(),
                'tanggal_kosong' => $kosong->count(),
                'contoh' => $kosong->take(5)->map(fn ($r) => (int) substr((string) $r->tanggal, 8, 2))->values()->all(),
            ]];
        })->all();
    }

    /**
     * Kelengkapan input per periode (per tanggal atau per bulan) untuk menandai grafik tren:
     * unit = unit-hari yang seharusnya diisi (dari periode aktif kendaraan), terisi = baris yang ada.
     * Hanya sampai hari ini. Tanpa tahun pada filter: kosong (tidak ditandai).
     *
     * @return array<string, array{unit:int, terisi:int}>  kunci = Y-m-d atau Y-m
     */
    public function kelengkapanPeriode(Filter $f, string $granularity): array
    {
        if (! $f->tahun) {
            return [];
        }
        $C = \Carbon\CarbonImmutable::class;
        if ($f->bulan) {
            $awal = $C::create($f->tahun, $f->bulan, 1);
            $akhir = $awal->endOfMonth()->startOfDay();
            if ($f->hari) {
                if (! checkdate($f->bulan, $f->hari, $f->tahun)) {
                    return [];
                }
                $awal = $akhir = $C::create($f->tahun, $f->bulan, $f->hari);
            }
        } else {
            $awal = $C::create($f->tahun, 1, 1);
            $akhir = $C::create($f->tahun, 12, 31);
        }
        $hariIni = $C::today();
        if ($akhir->greaterThan($hariIni)) {
            $akhir = $hariIni;
        }
        if ($awal->greaterThan($akhir)) {
            return [];
        }
        $n = (int) $awal->diffInDays($akhir) + 1;

        // Jumlah kendaraan beroperasi per tanggal: selisih kumulatif (+1 di hari mulai, -1 setelah hari terakhir).
        $selisih = array_fill(0, $n + 1, 0);
        $kendaraan = DB::table('kendaraan as k')->join('jenis_kendaraan as j', 'j.id', '=', 'k.jenis_kendaraan_id')
            ->when($f->bidang, fn ($q) => $q->where('j.bidang_id', $f->bidang->id))
            ->when($f->jenis, fn ($q) => $q->where('j.nama', $f->jenis))
            ->select('k.aktif_sejak', 'k.nonaktif_sejak')->get();
        foreach ($kendaraan as $k) {
            $mulai = $k->aktif_sejak ? $C::parse($k->aktif_sejak)->startOfDay() : $awal;
            $mulai = $mulai->greaterThan($awal) ? $mulai : $awal;
            $selesai = $k->nonaktif_sejak ? $C::parse($k->nonaktif_sejak)->startOfDay()->subDay() : $akhir;
            $selesai = $selesai->lessThan($akhir) ? $selesai : $akhir;
            if ($selesai->lessThan($mulai)) {
                continue;
            }
            $selisih[(int) $awal->diffInDays($mulai)]++;
            $selisih[(int) $awal->diffInDays($selesai) + 1]--;
        }
        $terisi = $this->withJenis($this->base($f), $f)
            ->selectRaw('s.tanggal t, COUNT(*) c')->groupBy('s.tanggal')->pluck('c', 't')
            ->mapWithKeys(fn ($c, $t) => [substr((string) $t, 0, 10) => (int) $c]);

        $hasil = [];
        $aktif = 0;
        for ($i = 0; $i < $n; $i++) {
            $aktif += $selisih[$i];
            $tgl = $awal->addDays($i)->toDateString();
            $kunci = $granularity === 'hari' ? $tgl : substr($tgl, 0, 7);
            $hasil[$kunci] ??= ['unit' => 0, 'terisi' => 0];
            $hasil[$kunci]['unit'] += $aktif;
            $hasil[$kunci]['terisi'] += min($terisi[$tgl] ?? 0, $aktif);
        }

        return $hasil;
    }

    /**
     * Gabungkan tren dengan kelengkapan: periode tanpa data tetap muncul (nilai kosong) dan
     * setiap periode diberi unit/terisi/lengkap agar grafik bisa menandai yang belum lengkap.
     */
    public function trenDenganKelengkapan(array $tren, array $kelengkapan): array
    {
        if (! $kelengkapan) {
            return $tren;
        }
        $perPeriode = collect($tren)->keyBy('periode');
        $hasil = [];
        foreach ($kelengkapan as $periode => $k) {
            $r = $perPeriode->get($periode) ?? ['periode' => $periode, 'jalan' => null, 'standby' => null, 'rusak' => null, 'hari' => 0, 'unit_hari' => 0];
            $hasil[] = $r + ['unit' => $k['unit'], 'terisi' => $k['terisi'], 'lengkap' => $k['terisi'] >= $k['unit']];
            $perPeriode->forget($periode);
        }
        // Data di luar rentang kelengkapan (mis. tanggal ke depan) tetap ditampilkan apa adanya.
        foreach ($perPeriode as $r) {
            $hasil[] = $r;
        }
        usort($hasil, fn ($a, $b) => strcmp($a['periode'], $b['periode']));

        return $hasil;
    }

    /**
     * Periode pembanding untuk KPI: bulan lalu (pilihan bulan) atau tahun lalu (pilihan tahun).
     * Bila periode yang dipilih masih berjalan, pembandingnya dipotong di tanggal yang sama
     * (mis. 1–16 November dibanding 1–16 Oktober) supaya jumlah jam & unit sebanding.
     *
     * @return array{label:string, sebagian:bool, availability:array}|null
     */
    public function pembanding(Filter $f): ?array
    {
        if (! $f->tahun || $f->hari) {
            return null;
        }
        $hariIni = \Carbon\CarbonImmutable::today();
        $bln = fn (\Carbon\CarbonImmutable $d) => ucfirst(mb_strtolower(JamKerja::namaBulan($d->month)));
        if ($f->bulan) {
            $awal = \Carbon\CarbonImmutable::create($f->tahun, $f->bulan, 1);
            $lalu = $awal->subMonthNoOverflow();
            $berjalan = $awal->isSameMonth($hariIni);
            $sampai = $berjalan ? $lalu->day(min($hariIni->day, $lalu->daysInMonth)) : null;
            $label = $bln($lalu).($lalu->year !== $f->tahun ? ' '.$lalu->year : '');
            if ($sampai) {
                $label = '1–'.$sampai->day.' '.mb_substr($bln($lalu), 0, 3).($lalu->year !== $f->tahun ? ' '.$lalu->year : '');
            }
            $pf = new Filter($lalu->year, $lalu->month, null, $f->bidang, $f->jenis, $sampai?->toDateString());
        } else {
            $lalu = $f->tahun - 1;
            $berjalan = $f->tahun === $hariIni->year;
            $sampai = $berjalan ? $hariIni->subYearNoOverflow() : null;
            $label = $sampai ? '1 Jan–'.$sampai->day.' '.mb_substr($bln($sampai), 0, 3).' '.$lalu : (string) $lalu;
            $pf = new Filter($lalu, null, null, $f->bidang, $f->jenis, $sampai?->toDateString());
        }

        return ['label' => $label, 'sebagian' => (bool) $sampai, 'availability' => $this->availability($pf)];
    }

    /**
     * Pengingat kelengkapan input: per bidang, berapa unit yang beroperasi pada tanggal tsb
     * (sesuai periode aktif kendaraan) tetapi belum punya data.
     *
     * @param  string[]  $tanggal  daftar tanggal Y-m-d
     * @return array<int, array{tanggal:string, bidang:array}>
     */
    public function kelengkapan(array $tanggal, ?\App\Models\User $user = null): array
    {
        $bidang = Bidang::ordered();
        $hasil = [];
        foreach ($tanggal as $t) {
            $q = DB::table('kendaraan as k')
                ->join('jenis_kendaraan as j', 'j.id', '=', 'k.jenis_kendaraan_id')
                ->leftJoin('sheet_data as s', fn ($on) => $on->on('s.kendaraan_id', '=', 'k.id')->where('s.tanggal', '=', $t))
                ->where(fn ($w) => $w->whereNull('k.aktif_sejak')->orWhereDate('k.aktif_sejak', '<=', $t))
                ->where(fn ($w) => $w->whereNull('k.nonaktif_sejak')->orWhereDate('k.nonaktif_sejak', '>', $t))
                ->selectRaw('j.bidang_id, COUNT(*) unit, COUNT(s.id) terisi, SUM(CASE WHEN s.jalan = 0 AND s.rusak = 0 THEN 1 ELSE 0 END) tanpa_jr')
                ->groupBy('j.bidang_id')->get()->keyBy('bidang_id');

            $hasil[] = [
                'tanggal' => $t,
                'bidang' => $bidang->map(function (Bidang $b) use ($q, $user) {
                    $r = $q->get($b->id);
                    $unit = (int) ($r->unit ?? 0);
                    $terisi = (int) ($r->terisi ?? 0);
                    $tanpaJr = (int) ($r->tanpa_jr ?? 0);

                    return [
                        'kode' => $b->kode,
                        'nama' => $b->nama_tampil,
                        'unit' => $unit,
                        'terisi' => $terisi,
                        'belum' => max(0, $unit - $terisi),
                        // Isian "kosong": tidak ada SATU unit pun yang tercatat Jalan atau Rusak pada tanggal itu.
                        // Tanda kolom bagian Bidang (Jalan & Rusak) belum diisi — termasuk form yang disimpan
                        // tanpa diisi (semua Standby 24 jam) maupun baris yang hanya diisi Perbaikan/PM.
                        'tanpa_jalan_rusak' => $tanpaJr,
                        'curiga_kosong' => $terisi > 0 && $tanpaJr === $terisi,
                        'bisa_input' => $user ? $user->canInput($b) : false,
                    ];
                })->values()->all(),
            ];
        }

        return $hasil;
    }

    /**
     * Kelengkapan satu bulan per bidang: unit-hari yang seharusnya diisi (dari periode aktif tiap
     * kendaraan, s.d. hari ini) dibanding unit-hari yang sudah terisi.
     */
    public function kelengkapanBulan(int $tahun, int $bulan): array
    {
        $awal = \Carbon\CarbonImmutable::create($tahun, $bulan, 1)->startOfDay();
        $akhir = $awal->endOfMonth()->startOfDay();
        $hariIni = \Carbon\CarbonImmutable::today();
        if ($akhir->greaterThan($hariIni)) {
            $akhir = $hariIni;
        }
        $seharusnya = [];
        if ($awal->lessThanOrEqualTo($akhir)) {
            $kendaraan = DB::table('kendaraan as k')->join('jenis_kendaraan as j', 'j.id', '=', 'k.jenis_kendaraan_id')
                ->select('j.bidang_id', 'k.aktif_sejak', 'k.nonaktif_sejak')->get();
            foreach ($kendaraan as $k) {
                $mulai = $k->aktif_sejak ? \Carbon\CarbonImmutable::parse($k->aktif_sejak)->startOfDay() : $awal;
                $mulai = $mulai->greaterThan($awal) ? $mulai : $awal;
                // nonaktif_sejak = hari pertama tidak beroperasi
                $selesai = $k->nonaktif_sejak ? \Carbon\CarbonImmutable::parse($k->nonaktif_sejak)->startOfDay()->subDay() : $akhir;
                $selesai = $selesai->lessThan($akhir) ? $selesai : $akhir;
                if ($selesai->greaterThanOrEqualTo($mulai)) {
                    $seharusnya[$k->bidang_id] = ($seharusnya[$k->bidang_id] ?? 0) + (int) $mulai->diffInDays($selesai) + 1;
                }
            }
        }
        $terisi = DB::table('sheet_data')->whereBetween('tanggal', [$awal->toDateString(), $awal->endOfMonth()->toDateString()])
            ->selectRaw('bidang_id, COUNT(*) c')->groupBy('bidang_id')->pluck('c', 'bidang_id');

        return Bidang::ordered()->mapWithKeys(function (Bidang $b) use ($seharusnya, $terisi) {
            $harus = (int) ($seharusnya[$b->id] ?? 0);
            $isi = (int) ($terisi[$b->id] ?? 0);

            return [$b->kode => [
                'seharusnya' => $harus,
                'terisi' => $isi,
                'persen' => $harus > 0 ? round(min($isi, $harus) / $harus * 100, 1) : null,
            ]];
        })->all();
    }
}
