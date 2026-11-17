<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Pencatat riwayat login. */
final class RiwayatLogin
{
    public const HASIL = [
        'berhasil' => 'Masuk', 'gagal' => 'Gagal masuk', 'nonaktif' => 'Ditolak (nonaktif)',
        'terkunci' => 'Terkunci sementara', 'keluar' => 'Keluar', 'keluar_otomatis' => 'Keluar otomatis',
    ];

    public static function catat(string $hasil, ?User $user, Request $request, ?string $isian = null): void
    {
        $ua = (string) $request->userAgent();
        DB::table('riwayat_login')->insert([
            'user_id' => $user?->id,
            'user_nama' => $user ? mb_substr((string) $user->name, 0, 100) : null,
            'isian' => $isian !== null ? mb_substr($isian, 0, 255) : null,
            'hasil' => $hasil,
            'ip' => $request->ip(),
            'perangkat' => self::perangkat($ua),
            'user_agent' => mb_substr($ua, 0, 500),
            'created_at' => now(),
        ]);
    }

    /** Ringkas user-agent menjadi "Browser · Sistem (HP)". */
    public static function perangkat(string $ua): string
    {
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            $ua === '' => 'Tidak diketahui',
            default => 'Browser lain',
        };
        $sistem = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => '',
        };
        $hp = preg_match('/Mobile|Android|iPhone/i', $ua) ? ' (HP)' : '';

        return trim($browser.($sistem ? ' · '.$sistem : '').$hp);
    }
}
