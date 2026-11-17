<?php

namespace App\Support;

use App\Models\Bidang;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * Filter standar dashboard (pengganti quick-filter Tableau: Tahun, Bulan, Tanggal, Bidang, Jenis).
 * Semua kondisi tanggal diterjemahkan ke rentang tanggal agar memakai index sheet_data(tanggal).
 */
final class Filter
{
    public function __construct(
        public readonly ?int $tahun = null,
        public readonly ?int $bulan = null,
        public readonly ?int $hari = null,
        public readonly ?Bidang $bidang = null,
        public readonly ?string $jenis = null,
        // Batas akhir tambahan (Y-m-d), dipakai pembanding "periode yang sama" bulan/tahun lalu,
        // dan bersama $mulai untuk rentang beberapa bulan (laporan & ekspor).
        public readonly ?string $sampai = null,
        public readonly ?string $mulai = null,
    ) {}

    /** Salinan filter dengan bidang lain (semua batas tanggal & rentang ikut terbawa). */
    public function denganBidang(?Bidang $bidang): self
    {
        return new self($this->tahun, $this->bulan, $this->hari, $bidang, $this->jenis, $this->sampai, $this->mulai);
    }

    /** Maksimal rentang laporan/ekspor, supaya query tetap ringan. */
    public const MAKS_BULAN = 24;

    /**
     * Rentang bulan dari parameter dari/ke (format YYYY-MM). Mengembalikan [awal, akhir] (awal bulan)
     * atau null bila tidak ada / tidak valid. Urutan terbalik ditukar; panjang dibatasi MAKS_BULAN.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public static function rentang(Request $request): ?array
    {
        $baca = function (string $k) use ($request) {
            $v = self::teks($request, $k);
            if (! preg_match('/^(\d{4})-(\d{2})$/', $v, $m) || (int) $m[2] < 1 || (int) $m[2] > 12 || (int) $m[1] < 2000 || (int) $m[1] > 2100) {
                return null;
            }

            return CarbonImmutable::create((int) $m[1], (int) $m[2], 1)->startOfDay();
        };
        $a = $baca('dari');
        $b = $baca('ke');
        if (! $a || ! $b) {
            return null;
        }
        if ($a->greaterThan($b)) {
            [$a, $b] = [$b, $a];
        }
        if ($a->diffInMonths($b) >= self::MAKS_BULAN) {
            $a = $b->subMonthsNoOverflow(self::MAKS_BULAN - 1);
        }

        return [$a, $b];
    }

    /**
     * Bidang yang wajib dipakai untuk laporan/ekspor: petugas bidang hanya bidangnya sendiri,
     * admin/manajemen/pemeliharaan bebas memilih (null = semua bidang).
     */
    public static function bidangTerkunci(?\App\Models\User $user): ?Bidang
    {
        $id = $user?->bidangRiwayat();

        return $id ? Bidang::find($id) : null;
    }

    public static function fromRequest(Request $request, ?Bidang $forceBidang = null, bool $defaultBulanIni = true): self
    {
        $now = CarbonImmutable::now();
        $tahun = $request->has('tahun') ? self::int(self::teks($request, 'tahun'), 2000, 2100) : $now->year;
        $bulan = $request->has('bulan') ? self::int(self::teks($request, 'bulan'), 1, 12) : ($defaultBulanIni ? $now->month : null);
        $hari = self::int(self::teks($request, 'hari'), 1, 31);

        $bidang = $forceBidang;
        // Ekspor/laporan: petugas bidang dikunci ke bidangnya (bukan hanya di tampilan).
        if (! $bidang && $request->boolean('ekspor')) {
            $bidang = self::bidangTerkunci($request->user());
        }
        if (! $bidang && self::teks($request, 'bidang') !== '') {
            $bidang = Bidang::where('kode', self::teks($request, 'bidang'))->first();
        }
        $jenis = self::teks($request, 'jenis') !== '' ? mb_strtoupper(self::teks($request, 'jenis')) : null;

        // Rentang beberapa bulan (dari/ke). Satu bulan saja diperlakukan seperti filter bulan biasa (kolom per tanggal).
        if ($r = self::rentang($request)) {
            [$a, $b] = $r;
            if ($a->equalTo($b)) {
                return new self($a->year, $a->month, null, $bidang, $jenis);
            }

            return new self(null, null, null, $bidang, $jenis, $b->endOfMonth()->toDateString(), $a->toDateString());
        }

        return new self($tahun, $bulan, $hari, $bidang, $jenis);
    }

    /** Parameter teks aman: array / nilai aneh dianggap kosong. */
    public static function teks(Request $request, string $key): string
    {
        $v = $request->input($key);

        return is_scalar($v) ? trim((string) $v) : '';
    }

    private static function int(mixed $v, int $min, int $max): ?int
    {
        if ($v === null || $v === '' || ! is_numeric($v)) {
            return null;
        }
        $v = (int) $v;

        return ($v >= $min && $v <= $max) ? $v : null;
    }

    /** Terapkan filter ke query yang memakai alias tabel sheet_data "s". */
    public function apply(Builder $q, string $alias = 's', bool $withBidang = true): Builder
    {
        $col = "{$alias}.tanggal";
        if ($this->tahun) {
            if ($this->bulan && $this->hari && checkdate($this->bulan, $this->hari, $this->tahun)) {
                $q->where($col, sprintf('%04d-%02d-%02d', $this->tahun, $this->bulan, $this->hari));
            } elseif ($this->bulan) {
                $start = CarbonImmutable::create($this->tahun, $this->bulan, 1);
                $q->whereBetween($col, [$start->toDateString(), $start->endOfMonth()->toDateString()]);
                if ($this->hari) {
                    $q->whereRaw('1 = 0'); // tanggal tidak valid (mis. 31 Februari)
                }
            } else {
                $q->whereBetween($col, ["{$this->tahun}-01-01", "{$this->tahun}-12-31"]);
                if ($this->hari) {
                    $q->whereDay($col, $this->hari);
                }
            }
        } else {
            if ($this->bulan) {
                $q->whereMonth($col, $this->bulan);
            }
            if ($this->hari) {
                $q->whereDay($col, $this->hari);
            }
        }
        if ($this->mulai) {
            $q->where($col, '>=', $this->mulai);
        }
        if ($this->sampai) {
            $q->where($col, '<=', $this->sampai);
        }
        if ($withBidang && $this->bidang) {
            $q->where("{$alias}.bidang_id", $this->bidang->id);
        }

        return $q;
    }

    public function label(): string
    {
        if ($this->mulai && $this->sampai && ! $this->tahun) {
            return self::labelRentang(CarbonImmutable::parse($this->mulai), CarbonImmutable::parse($this->sampai));
        }
        $parts = [];
        if ($this->hari) {
            $parts[] = $this->hari;
        }
        $parts[] = $this->bulan ? ucfirst(mb_strtolower(JamKerja::namaBulan($this->bulan))) : 'Semua Bulan';
        $parts[] = $this->tahun ?: 'Semua Tahun';

        return implode(' ', $parts);
    }

    /** "Agustus – Oktober 2026" atau "November 2025 – Januari 2026". */
    public static function labelRentang(CarbonImmutable $a, CarbonImmutable $b): string
    {
        $bln = fn (CarbonImmutable $d) => ucfirst(mb_strtolower(JamKerja::namaBulan($d->month)));
        if ($a->isSameMonth($b)) {
            return $bln($a).' '.$a->year;
        }

        return $a->year === $b->year ? $bln($a).' – '.$bln($b).' '.$b->year : $bln($a).' '.$a->year.' – '.$bln($b).' '.$b->year;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'tahun' => $this->tahun, 'bulan' => $this->bulan, 'hari' => $this->hari,
            'bidang' => $this->bidang?->kode, 'bidang_nama' => $this->bidang?->nama_tampil, 'jenis' => $this->jenis, 'label' => $this->label(),
            'dari' => $this->mulai ? substr($this->mulai, 0, 7) : null, 'ke' => ($this->mulai && $this->sampai) ? substr($this->sampai, 0, 7) : null,
        ];
    }
}
