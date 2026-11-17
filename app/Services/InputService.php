<?php

namespace App\Services;

use App\Models\Bidang;
use App\Models\Kendaraan;
use App\Models\SheetData;
use App\Models\User;
use App\Support\JamKerja;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InputService
{
    /** Kolom angka yang dicatat di riwayat perubahan. */
    public const KOLOM_RIWAYAT = ['jalan', 'standby', 'rusak', 'perbaikan', 'pm'];

    /** Data grid input untuk satu bidang & tanggal. */
    public function grid(Bidang $bidang, string $tanggal, User $user): array
    {
        $existing = SheetData::query()
            ->where('bidang_id', $bidang->id)->where('tanggal', $tanggal)
            ->with('updater:id,name')->get()->keyBy('kendaraan_id');

        // Kendaraan yang beroperasi pada tanggal ini (sesuai periode aktif),
        // ditambah kendaraan yang sudah punya data di tanggal tsb walau kini nonaktif.
        $kendaraan = Kendaraan::query()
            ->join('jenis_kendaraan as j', 'j.id', '=', 'kendaraan.jenis_kendaraan_id')
            ->where('j.bidang_id', $bidang->id)
            ->where(fn ($q) => $q->where(fn ($a) => $a->aktifPada($tanggal))->orWhereIn('kendaraan.id', $existing->keys()))
            ->select('kendaraan.id', 'kendaraan.nomor_kendaraan', 'kendaraan.is_active', 'kendaraan.aktif_sejak', 'kendaraan.nonaktif_sejak', 'j.nama as jenis')
            ->get();

        // Data lama yang kendaraannya kini sudah pindah bidang tetap ditampilkan (snapshot).
        $missing = $existing->keys()->diff($kendaraan->pluck('id'));
        if ($missing->isNotEmpty()) {
            $extra = Kendaraan::query()->whereIn('kendaraan.id', $missing)
                ->join('jenis_kendaraan as j', 'j.id', '=', 'kendaraan.jenis_kendaraan_id')
                ->select('kendaraan.id', 'kendaraan.nomor_kendaraan', 'kendaraan.is_active', 'kendaraan.aktif_sejak', 'kendaraan.nonaktif_sejak', 'j.nama as jenis')->get();
            $kendaraan = $kendaraan->concat($extra);
        }

        $rows = $kendaraan->map(function ($k) use ($existing, $tanggal) {
            $s = $existing->get($k->id);

            return [
                'kendaraan_id' => $k->id,
                'jenis' => $k->jenis,
                'nomor' => $k->nomor_kendaraan,
                'aktif' => $k->aktifPada($tanggal),
                // Baris otomatis (Standby 24 jam dari sistem) dianggap belum diisi petugas.
                'tersimpan' => $s && ! $s->otomatis,
                'otomatis' => (bool) $s?->otomatis,
                'jalan' => $s->jalan ?? 0,
                'standby' => $s->standby ?? JamKerja::JAM_PER_HARI,
                'rusak' => $s->rusak ?? 0,
                'perbaikan' => $s->perbaikan ?? 0,
                'pm' => $s->pm ?? 0,
                'total_jam_kerja' => $s->total_jam_kerja ?? JamKerja::JAM_PER_HARI,
                'diperbarui' => $s && ! $s->otomatis ? $s->updated_at?->format('Y-m-d H:i:s') : null,
                'oleh' => $s && ! $s->otomatis ? $s->updater?->name : null,
            ];
        })->sort(fn ($a, $b) => strnatcasecmp($a['nomor'], $b['nomor']) ?: strcmp($a['jenis'], $b['jenis']))->values();

        $diisi = $existing->reject(fn ($s) => $s->otomatis);
        $last = $diisi->sortByDesc('updated_at')->first();

        return [
            'tanggal' => $tanggal,
            'bidang' => ['kode' => $bidang->kode, 'nama' => $bidang->nama_tampil],
            'kolom_edit' => $user->editableColumns($bidang),
            'rows' => $rows,
            'jumlah_tersimpan' => $diisi->count(),
            'terakhir' => $last ? ['waktu' => $last->updated_at->format('Y-m-d H:i:s'), 'oleh' => $last->updater?->name] : null,
            'terakhir_bidang' => $this->lastUpdated($bidang),
        ];
    }

    public function lastUpdated(Bidang $bidang): ?array
    {
        $r = SheetData::where('bidang_id', $bidang->id)->where('otomatis', false)->orderByDesc('updated_at')->with('updater:id,name')
            ->first(['id', 'tanggal', 'updated_at', 'updated_by']);

        return $r ? ['waktu' => $r->updated_at->format('Y-m-d H:i:s'), 'tanggal' => $r->tanggal->format('Y-m-d'), 'oleh' => $r->updater?->name] : null;
    }

    /**
     * Simpan input. $rows: [{kendaraan_id, jalan?, rusak?, perbaikan?, pm?, asal?: {kolom: nilai saat dimuat}}]
     * - Hanya kolom yang boleh diedit user yang dipakai; kolom lain diambil dari database (bukan dari browser).
     * - Konflik: bila nilai kolom yang sama sudah diubah orang lain sejak halaman dimuat -> ditolak (409).
     * - Semua-atau-tidak sama sekali (transaksi).
     *
     * @return array{status:string, disimpan?:int, dibuat?:int, tidak_berubah?:int, errors?:array, konflik?:array}
     */
    public function simpan(Bidang $bidang, string $tanggal, array $rows, User $user): array
    {
        $kolomEdit = $user->editableColumns($bidang);
        if (! $kolomEdit) {
            return ['status' => 'forbidden'];
        }

        $ids = collect($rows)->pluck('kendaraan_id')->map(fn ($v) => (int) $v)->unique()->values();
        $kendaraan = Kendaraan::query()->whereIn('kendaraan.id', $ids)
            ->join('jenis_kendaraan as j', 'j.id', '=', 'kendaraan.jenis_kendaraan_id')
            ->select('kendaraan.id', 'kendaraan.nomor_kendaraan', 'kendaraan.jenis_kendaraan_id', 'kendaraan.aktif_sejak', 'kendaraan.nonaktif_sejak', 'j.bidang_id')
            ->get()->keyBy('id');

        return DB::transaction(function () use ($bidang, $tanggal, $rows, $user, $kolomEdit, $kendaraan, $ids) {
            $existing = SheetData::where('tanggal', $tanggal)->whereIn('kendaraan_id', $ids)
                ->lockForUpdate()->get()->keyBy('kendaraan_id');

            $errors = $konflik = $toSave = [];
            foreach ($rows as $row) {
                $kid = (int) ($row['kendaraan_id'] ?? 0);
                $k = $kendaraan->get($kid);
                $s = $existing->get($kid);
                $nomor = $k->nomor_kendaraan ?? "#{$kid}";

                if (! $k) {
                    $errors[] = ['kendaraan_id' => $kid, 'nomor' => $nomor, 'pesan' => 'Kendaraan tidak ditemukan.'];
                    continue;
                }
                $milikBidang = $s ? $s->bidang_id === $bidang->id : $k->bidang_id === $bidang->id;
                if (! $milikBidang) {
                    $errors[] = ['kendaraan_id' => $kid, 'nomor' => $nomor, 'pesan' => 'Bukan kendaraan bidang ini.'];
                    continue;
                }
                // Data baru hanya boleh dibuat untuk tanggal saat kendaraan beroperasi.
                if (! $s && ! $k->aktifPada($tanggal)) {
                    $errors[] = ['kendaraan_id' => $kid, 'nomor' => $nomor, 'pesan' => 'Kendaraan tidak beroperasi pada tanggal ini.'];
                    continue;
                }

                $nilai = [
                    'jalan' => $s->jalan ?? 0, 'rusak' => $s->rusak ?? 0,
                    'perbaikan' => $s->perbaikan ?? 0, 'pm' => $s->pm ?? 0,
                ];
                $bentrok = [];
                foreach ($kolomEdit as $kol) {
                    if (! array_key_exists($kol, $row)) {
                        continue;
                    }
                    $v = $row[$kol];
                    if (! is_numeric($v) || (int) $v != $v || $v < 0 || $v > 24) {
                        $errors[] = ['kendaraan_id' => $kid, 'nomor' => $nomor, 'pesan' => "{$kol} harus angka bulat 0–24."];
                        continue 2;
                    }
                    // Deteksi konflik: nilai di DB sudah tidak sama dengan nilai saat user memuat halaman.
                    if (isset($row['asal']) && is_array($row['asal']) && array_key_exists($kol, $row['asal'])
                        && (int) $row['asal'][$kol] !== (int) $nilai[$kol] && (int) $v !== (int) $nilai[$kol]) {
                        $bentrok[] = $kol;
                    }
                    $nilai[$kol] = (int) $v;
                }
                if ($bentrok) {
                    $konflik[] = ['kendaraan_id' => $kid, 'nomor' => $nomor, 'kolom' => $bentrok,
                        'oleh' => $s?->updater?->name, 'waktu' => $s?->updated_at?->format('H:i')];
                    continue;
                }

                try {
                    $hasil = JamKerja::hitung($nilai['jalan'], $nilai['rusak'], $nilai['perbaikan'], $nilai['pm']);
                } catch (InvalidArgumentException $e) {
                    $errors[] = ['kendaraan_id' => $kid, 'nomor' => $nomor, 'pesan' => $e->getMessage()];
                    continue;
                }
                $toSave[] = [$k, $s, $hasil];
            }

            if ($errors) {
                return ['status' => 'invalid', 'errors' => $errors];
            }
            if ($konflik) {
                return ['status' => 'conflict', 'konflik' => $konflik];
            }

            $dibuat = $diubah = $sama = 0;
            $riwayat = [];
            $catat = function (SheetData $row, string $aksi, ?array $sebelum) use (&$riwayat, $user, $tanggal) {
                $riwayat[] = [
                    'sheet_data_id' => $row->id,
                    'tanggal' => $tanggal,
                    'kendaraan_id' => $row->kendaraan_id,
                    'nomor_kendaraan' => $row->getRelationValue('kendaraan')?->nomor_kendaraan ?? '',
                    'bidang_id' => $row->bidang_id,
                    'aksi' => $aksi,
                    'sebelum' => $sebelum ? json_encode($sebelum) : null,
                    'sesudah' => json_encode(array_map('intval', $row->only(self::KOLOM_RIWAYAT))),
                    'user_id' => $user->id,
                    'user_nama' => mb_substr((string) $user->name, 0, 100),
                    'ip' => request()?->ip(),
                    'created_at' => now(),
                ];
            };
            foreach ($toSave as [$k, $s, $hasil]) {
                if ($s && $s->otomatis) {
                    // Baris otomatis (Standby 24 jam) yang disimpan petugas: dicatat sebagai isian pertama,
                    // walaupun angkanya tidak berubah (petugas memastikan unit memang Standby).
                    $s->fill($hasil + ['otomatis' => false, 'created_by' => $user->id, 'updated_by' => $user->id]);
                    $s->save();
                    $s->setRelation('kendaraan', $k);
                    $catat($s, 'buat', null);
                    $dibuat++;
                } elseif ($s) {
                    $sebelum = array_map('intval', $s->only(self::KOLOM_RIWAYAT));
                    $s->fill($hasil);
                    if ($s->isDirty()) {
                        $s->updated_by = $user->id;
                        $s->save();
                        $s->setRelation('kendaraan', $k);
                        $catat($s, 'ubah', $sebelum);
                        $diubah++;
                    } else {
                        $sama++;
                    }
                } else {
                    $baru = SheetData::create($hasil + [
                        'tanggal' => $tanggal,
                        'kendaraan_id' => $k->id,
                        'bidang_id' => $bidang->id,
                        'jenis_kendaraan_id' => $k->jenis_kendaraan_id,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                    $baru->setRelation('kendaraan', $k);
                    $catat($baru, 'buat', null);
                    $dibuat++;
                }
            }
            foreach (array_chunk($riwayat, 500) as $batch) {
                DB::table('riwayat_input')->insert($batch);
            }
            Cache::forget('dms:data-version');

            return ['status' => 'ok', 'dibuat' => $dibuat, 'disimpan' => $diubah, 'tidak_berubah' => $sama];
        });
    }
}
