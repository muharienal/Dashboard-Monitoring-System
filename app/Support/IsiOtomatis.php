<?php

namespace App\Support;

use App\Models\Kendaraan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Data harian otomatis: setiap kendaraan yang beroperasi pada suatu tanggal otomatis punya baris
 * Standby 24 jam (otomatis = 1). Petugas cukup mengubah unit yang Jalan / Rusak / Perbaikan / PM.
 *
 *  - Diisi mulai tanggal "isi_otomatis_sejak" (tabel pengaturan / DMS_ISI_OTOMATIS_SEJAK) s.d. hari ini.
 *  - Data yang sudah ada (diisi petugas / dari DMS lama) tidak pernah ditimpa.
 *  - Dijalankan otomatis saat ada pengguna membuka DMS (paling sering sekali per 10 menit) dan
 *    setiap kali data kendaraan berubah.
 */
final class IsiOtomatis
{
    private const KUNCI_CACHE = 'dms:isi-otomatis';

    public static function sejak(): ?string
    {
        $v = config('dms.isi_otomatis_sejak') ?: DB::table('pengaturan')->where('kunci', 'isi_otomatis_sejak')->value('nilai');

        return $v ? CarbonImmutable::parse($v)->toDateString() : null;
    }

    /** Pastikan data otomatis sudah ada s.d. hari ini. Aman dipanggil berkali-kali. Mengembalikan jumlah baris baru. */
    public static function pastikan(bool $paksa = false): int
    {
        $hariIni = CarbonImmutable::today()->toDateString();
        if (! $paksa && Cache::get(self::KUNCI_CACHE) === $hariIni) {
            return 0;
        }
        $baru = 0;
        try {
            $sejak = self::sejak();
            if ($sejak && $sejak <= $hariIni) {
                $baru = self::isi($sejak, $hariIni);
            }
            Cache::put(self::KUNCI_CACHE, $hariIni, now()->addMinutes(10));
        } catch (\Throwable $e) {
            Log::warning('Isi otomatis data harian gagal: '.$e->getMessage());
        }

        return $baru;
    }

    /** Hapus tanda "sudah diisi hari ini" supaya pengisian dijalankan ulang (mis. setelah data kendaraan berubah). */
    public static function lupakan(): void
    {
        Cache::forget(self::KUNCI_CACHE);
    }

    /**
     * Rapikan baris otomatis satu kendaraan setelah datanya berubah (pindah jenis, periode aktif berubah,
     * dinonaktifkan): baris otomatis di luar periode aktif / dengan jenis lama dihapus, lalu diisi ulang.
     */
    public static function rapikan(Kendaraan $k): void
    {
        $k->refresh();
        DB::table('sheet_data')->where('kendaraan_id', $k->id)->where('otomatis', true)
            ->where(function ($q) use ($k) {
                $q->where('jenis_kendaraan_id', '!=', $k->jenis_kendaraan_id);
                if ($k->aktif_sejak) {
                    $q->orWhere('tanggal', '<', $k->aktif_sejak->toDateString());
                }
                if ($k->nonaktif_sejak) {
                    $q->orWhere('tanggal', '>=', $k->nonaktif_sejak->toDateString());
                } elseif (! $k->is_active) {
                    $q->orWhereRaw('1 = 1');
                }
            })->delete();
        self::lupakan();
        self::pastikan(true);
    }

    /** Sisipkan baris Standby 24 jam untuk setiap kendaraan beroperasi yang belum punya data, per tanggal. */
    private static function isi(string $dari, string $sampai): int
    {
        $baru = 0;
        $waktu = now()->format('Y-m-d H:i:s');
        $tanggal = [];
        for ($t = CarbonImmutable::parse($dari); $t->toDateString() <= $sampai; $t = $t->addDay()) {
            $tanggal[] = $t->toDateString();
        }
        foreach (array_chunk($tanggal, 31) as $potong) {
            $hari = implode(' UNION ALL ', array_map(fn ($t) => "SELECT '{$t}' AS t", $potong));
            $sumber = DB::table('kendaraan as k')
                ->join('jenis_kendaraan as j', 'j.id', '=', 'k.jenis_kendaraan_id')
                ->crossJoin(DB::raw("({$hari}) d"))
                ->where(fn ($w) => $w->where('k.is_active', true)->orWhereNotNull('k.nonaktif_sejak'))
                ->where(fn ($w) => $w->whereNull('k.aktif_sejak')->orWhereRaw('DATE(k.aktif_sejak) <= d.t'))
                ->where(fn ($w) => $w->whereNull('k.nonaktif_sejak')->orWhereRaw('DATE(k.nonaktif_sejak) > d.t'))
                ->whereNotExists(fn ($q) => $q->from('sheet_data as s')->whereColumn('s.kendaraan_id', 'k.id')->whereRaw('s.tanggal = d.t'))
                ->selectRaw("d.t, k.id, j.bidang_id, k.jenis_kendaraan_id, 0, 24, 0, 0, 0, 24, 100, 0, 1, 0, 1, '{$waktu}', '{$waktu}'");
            $baru += DB::table('sheet_data')->insertOrIgnoreUsing(
                ['tanggal', 'kendaraan_id', 'bidang_id', 'jenis_kendaraan_id', 'jalan', 'standby', 'rusak', 'perbaikan', 'pm',
                    'total_jam_kerja', 'availability_rate', 'utilitas_jalan', 'utilitas_standby', 'utilitas_rusak', 'otomatis', 'created_at', 'updated_at'],
                $sumber,
            );
        }
        if ($baru > 0) {
            Cache::forget('dms:data-version');
        }

        return $baru;
    }
}
