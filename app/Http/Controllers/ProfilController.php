<?php

namespace App\Http\Controllers;

use App\Support\RiwayatMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class ProfilController extends Controller
{
    public function edit(\Illuminate\Http\Request $request)
    {
        // 10 aktivitas login terakhir akun ini, agar pengguna sadar bila akunnya dipakai orang lain.
        $login = DB::table('riwayat_login')->where('user_id', $request->user()->id)->orderByDesc('created_at')->orderByDesc('id')->limit(10)->get();

        return view('akun.profil', compact('login'));
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'password_lama' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], ['password_lama.current_password' => 'Password sekarang salah.']);

        $user = $request->user();
        $user->update(['password' => $request->input('password')]);
        RiwayatMaster::catat('akun', $user->id, $user->name, 'password', null, null, 'Diganti sendiri lewat menu Ganti password');
        // Akhiri sesi lain milik user ini.
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();

        return back()->with('success', 'Password diganti.');
    }
}
