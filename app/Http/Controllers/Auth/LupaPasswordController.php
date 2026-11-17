<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PermintaanResetPassword;
use App\Models\User;
use App\Support\NamaPengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * "Lupa password" dari halaman login: mengirim permintaan ke admin.
 * Pesan selalu sama, ada atau tidaknya akun, supaya nama pengguna tidak bisa ditebak-tebak.
 */
class LupaPasswordController extends Controller
{
    public const PESAN = 'Permintaan terkirim. Admin akan mengatur password baru dan memberitahukannya kepada Anda.';

    public function show()
    {
        return view('auth.lupa-password');
    }

    public function kirim(Request $request)
    {
        $kunci = 'lupa-password|'.$request->ip();
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $menit = (int) ceil(RateLimiter::availableIn($kunci) / 60);

            return back()->withInput()->with('error', "Terlalu banyak permintaan. Coba lagi dalam {$menit} menit.");
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ], [], ['username' => 'Nama pengguna', 'catatan' => 'Catatan']);
        RateLimiter::hit($kunci, 600);

        $user = User::where('username', NamaPengguna::normal($data['username']))->where('is_active', true)->first();
        if ($user) {
            $catatan = isset($data['catatan']) ? trim($data['catatan']) : null;
            $ada = PermintaanResetPassword::menunggu()->where('user_id', $user->id)->latest('id')->first();
            if ($ada) {
                // Permintaan ulang tidak menumpuk; cukup perbarui waktu & catatan.
                $ada->forceFill(['catatan' => $catatan ?: $ada->catatan, 'ip' => $request->ip()])->touch();
            } else {
                PermintaanResetPassword::create(['user_id' => $user->id, 'catatan' => $catatan ?: null, 'ip' => $request->ip(), 'status' => PermintaanResetPassword::MENUNGGU]);
            }
        }

        return redirect()->route('login')->with('success', self::PESAN);
    }
}
