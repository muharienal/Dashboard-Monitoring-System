<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Kendaraan extends Model
{
    protected $table = 'kendaraan';

    protected $fillable = ['jenis_kendaraan_id', 'nomor_kendaraan', 'is_active', 'aktif_sejak', 'nonaktif_sejak', 'keterangan'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'aktif_sejak' => 'date', 'nonaktif_sejak' => 'date'];
    }

    /** Kendaraan yang beroperasi pada tanggal tertentu (muncul di form input tanggal tsb). */
    public function scopeAktifPada(Builder $q, string $tanggal, string $tabel = 'kendaraan'): Builder
    {
        return $q->where(fn ($w) => $w->whereNull("{$tabel}.aktif_sejak")->orWhereDate("{$tabel}.aktif_sejak", '<=', $tanggal))
            ->where(fn ($w) => $w->whereNull("{$tabel}.nonaktif_sejak")->orWhereDate("{$tabel}.nonaktif_sejak", '>', $tanggal));
    }

    public function aktifPada(string $tanggal): bool
    {
        return (! $this->aktif_sejak || $this->aktif_sejak->toDateString() <= $tanggal)
            && (! $this->nonaktif_sejak || $this->nonaktif_sejak->toDateString() > $tanggal);
    }

    /**
     * Mengisi periode aktif yang masih kosong (dipakai migration & import data lama):
     *  aktif_sejak    = tanggal data pertama, atau tanggal dibuat bila belum ada data
     *  nonaktif_sejak = sehari setelah data terakhir, untuk kendaraan yang sudah nonaktif
     */
    public static function isiPeriodeAktifKosong(): void
    {
        DB::table('kendaraan')->whereNull('aktif_sejak')->update([
            'aktif_sejak' => DB::raw('(SELECT MIN(s.tanggal) FROM sheet_data s WHERE s.kendaraan_id = kendaraan.id)'),
        ]);
        foreach (DB::table('kendaraan')->whereNull('aktif_sejak')->get(['id', 'created_at']) as $k) {
            DB::table('kendaraan')->where('id', $k->id)->update(['aktif_sejak' => Carbon::parse($k->created_at ?? now())->toDateString()]);
        }
        $akhir = DB::table('sheet_data')->selectRaw('kendaraan_id, MAX(tanggal) t')->groupBy('kendaraan_id')->pluck('t', 'kendaraan_id');
        foreach (DB::table('kendaraan')->where('is_active', false)->whereNull('nonaktif_sejak')->pluck('id') as $id) {
            $tgl = isset($akhir[$id]) ? Carbon::parse($akhir[$id])->addDay() : now();
            DB::table('kendaraan')->where('id', $id)->update(['nonaktif_sejak' => $tgl->toDateString()]);
        }
    }

    public function jenis(): BelongsTo
    {
        return $this->belongsTo(JenisKendaraan::class, 'jenis_kendaraan_id');
    }

    public function sheetData(): HasMany
    {
        return $this->hasMany(SheetData::class);
    }

    /** Normalisasi nomor: trim, spasi ganda dihapus, huruf besar. */
    public static function normalizeNomor(string $nomor): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', ' ', trim($nomor)));
    }
}
