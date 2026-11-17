<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Auth\LoginController;
use App\Support\RiwayatLogin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keluar otomatis bila pengguna tidak aktif lebih dari config('dms.idle_menit').
 * "Aktif" = membuka halaman, mengirim data (POST/PUT/...), atau sinyal aktivitas dari browser
 * (mouse/keyboard/sentuh). Permintaan data otomatis di latar (polling) tidak dihitung.
 * Mode TV dikecualikan.
 */
class KeluarJikaTidakAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $menit = (int) config('dms.idle_menit');
        $user = $request->user();
        if (! $user || $menit <= 0 || ! $request->hasSession()) {
            return $next($request);
        }
        $sesi = $request->session();
        $tv = $request->header('X-DMS-Mode') === 'tv' || $request->routeIs('tv', 'tv.*') || $sesi->get('mode_tv');
        $kini = now()->getTimestamp();
        $terakhir = (int) $sesi->get('aktif_terakhir', 0);

        if (! $tv && $terakhir && $kini - $terakhir > $menit * 60) {
            RiwayatLogin::catat('keluar_otomatis', $user, $request);
            Auth::guard('web')->logout();
            $sesi->invalidate();
            $sesi->regenerateToken();
            $pesan = LoginController::pesanTidakAktif();

            return $request->expectsJson()
                ? response()->json(['message' => $pesan], 401)
                : redirect()->route('login')->with('warning', $pesan);
        }

        if ($tv || ! $terakhir || ! $request->isMethod('GET') || ! $request->expectsJson()) {
            $sesi->put('aktif_terakhir', $kini);
        }

        return $next($request);
    }
}
