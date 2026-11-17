<?php

namespace App\Support;

use App\Models\Bidang;
use App\Models\JenisKendaraan;
use App\Models\Kendaraan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Pencatat riwayat perubahan data master (kendaraan, jenis, bidang, akun). */
final class RiwayatMaster
{
    public const OBJEK = ['kendaraan' => 'Kendaraan', 'jenis' => 'Jenis', 'bidang' => 'Bidang', 'akun' => 'Akun'];

    public const AKSI = [
        'tambah' => 'Ditambah', 'ubah' => 'Diubah', 'hapus' => 'Dihapus', 'nonaktif' => 'Dinonaktifkan',
        'aktif' => 'Diaktifkan', 'password' => 'Password diganti', 'keluar' => 'Dipaksa keluar', 'reset_tolak' => 'Reset ditolak',
    ];

    private const LABEL = [
        'nomor' => 'Nomor', 'jenis' => 'Jenis', 'bidang' => 'Bidang', 'keterangan' => 'Keterangan', 'mulai' => 'Mulai beroperasi',
        'nonaktif' => 'Nonaktif mulai', 'status' => 'Status', 'nama' => 'Nama', 'username' => 'Nama pengguna', 'email' => 'Email', 'role' => 'Role',
        'nama_tampil' => 'Nama di menu', 'urutan' => 'Urutan', 'semua_kolom' => 'Semua kolom bebas diisi',
    ];

    public static function kendaraan(Kendaraan $k): array
    {
        $j = JenisKendaraan::with('bidang')->find($k->jenis_kendaraan_id);

        return [
            'nomor' => $k->nomor_kendaraan,
            'jenis' => $j?->nama,
            'bidang' => $j?->bidang?->nama_tampil,
            'keterangan' => $k->keterangan ?: null,
            'mulai' => self::tgl($k->aktif_sejak),
            'nonaktif' => self::tgl($k->nonaktif_sejak),
            'status' => $k->is_active ? 'Aktif' : 'Nonaktif',
        ];
    }

    public static function akun(User $u): array
    {
        $b = $u->bidang_id ? Bidang::find($u->bidang_id) : null;

        return [
            'nama' => $u->name,
            'username' => $u->username,
            'role' => User::ROLES[$u->role] ?? $u->role,
            'bidang' => $b?->nama_tampil,
            'status' => $u->is_active ? 'Aktif' : 'Nonaktif',
        ];
    }

    public static function jenis(JenisKendaraan $j): array
    {
        return ['nama' => $j->nama, 'bidang' => Bidang::find($j->bidang_id)?->nama_tampil];
    }

    public static function bidang(Bidang $b): array
    {
        return ['nama_tampil' => $b->nama_tampil, 'urutan' => (string) $b->urutan, 'semua_kolom' => $b->input_semua_kolom ? 'Ya' : 'Tidak'];
    }

    /**
     * Catat satu perubahan. Untuk "ubah" tanpa kolom yang berubah (dan tanpa catatan) tidak dicatat.
     *
     * @param  array<string,?string>|null  $sebelum
     * @param  array<string,?string>|null  $sesudah
     */
    public static function catat(string $objek, ?int $id, string $nama, string $aksi, ?array $sebelum = null, ?array $sesudah = null, ?string $catatan = null): void
    {
        $perubahan = [];
        foreach (array_keys(($sesudah ?? []) + ($sebelum ?? [])) as $k) {
            $dari = $sebelum === null ? null : ($sebelum[$k] ?? null);
            $ke = $sesudah === null ? null : ($sesudah[$k] ?? null);
            if ((string) $dari === (string) $ke || ($dari === null && $ke === null)) {
                continue;
            }
            $perubahan[] = ['kolom' => $k, 'label' => self::LABEL[$k] ?? $k, 'dari' => $dari, 'ke' => $ke];
        }
        if ($aksi === 'ubah' && ! $perubahan && ! $catatan) {
            return;
        }
        $u = auth()->user();
        DB::table('riwayat_master')->insert([
            'objek' => $objek,
            'objek_id' => $id,
            'objek_nama' => mb_substr($nama, 0, 150),
            'aksi' => $aksi,
            'perubahan' => $perubahan ? json_encode($perubahan, JSON_UNESCAPED_UNICODE) : null,
            'catatan' => $catatan ? mb_substr($catatan, 0, 255) : null,
            'user_id' => $u?->id,
            'user_nama' => $u ? mb_substr((string) $u->name, 0, 100) : null,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    private static function tgl(mixed $d): ?string
    {
        return $d ? Carbon::parse($d)->translatedFormat('j M Y') : null;
    }
}
