<?php

namespace App\Http\Controllers;

use App\Models\Bidang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Mode layar TV: tampilan layar penuh yang berganti antar bidang, untuk monitor ruang kontrol. */
class TvController extends Controller
{
    public function show(Request $request)
    {
        // Penanda: layar ini mode TV (tidak dikeluarkan otomatis karena "tidak aktif").
        $request->session()->put('mode_tv', true);

        return view('tv', [
            'bidang' => Bidang::ordered()->map(fn ($b) => ['kode' => $b->kode, 'nama' => $b->nama_tampil])->values(),
            'detik' => max(10, min(300, (int) $request->query('detik', 30))),
        ]);
    }

    /** "Tetap masuk di layar ini": login diingat (cookie) agar TV tidak perlu login ulang walau sesi habis. */
    public function ingat(Request $request)
    {
        $user = $request->user();
        Auth::guard('web')->login($user, true);
        $request->session()->put('mode_tv', true);

        return response()->json(['ok' => true, 'pesan' => "Layar ini akan tetap masuk sebagai {$user->name}."]);
    }
}
