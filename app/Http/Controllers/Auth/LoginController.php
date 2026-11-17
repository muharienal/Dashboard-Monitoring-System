<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\NamaPengguna;
use App\Support\RiwayatLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        // Login memakai nama pengguna ("admin"). Isian lama berbentuk email ("admin@gmail.com") tetap diterima.
        $isianAsli = $request->input('username');
        $request->merge(['username' => NamaPengguna::normal($isianAsli)]);
        // Saat gagal, kolom diisi kembali persis seperti yang diketik.
        $kembalikan = function (ValidationException $e) use ($request, $isianAsli) {
            $request->merge(['username' => is_string($isianAsli) ? trim($isianAsli) : $isianAsli]);

            return $e;
        };

        try {
            $data = $request->validate([
                'username' => ['required', 'string', 'max:50'],
                'password' => ['required', 'string'],
            ], [], ['username' => 'Nama pengguna', 'password' => 'Password']);
        } catch (ValidationException $e) {
            throw $kembalikan($e);
        }

        $key = $data['username'].'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $detik = RateLimiter::availableIn($key);
            RiwayatLogin::catat('terkunci', User::where('username', $data['username'])->first(), $request, is_string($isianAsli) ? $isianAsli : null);
            throw $kembalikan(ValidationException::withMessages(['username' => "Terlalu banyak percobaan. Coba lagi dalam {$detik} detik."]));
        }

        if (! Auth::attempt(['username' => $data['username'], 'password' => $data['password'], 'is_active' => true])) {
            RateLimiter::hit($key, 60);
            $calon = User::where('username', $data['username'])->first();
            RiwayatLogin::catat($calon && ! $calon->is_active ? 'nonaktif' : 'gagal', $calon, $request, is_string($isianAsli) ? $isianAsli : null);
            throw $kembalikan(ValidationException::withMessages(['username' => 'Nama pengguna atau password salah.'])
                ->redirectTo(route('login')));
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $user = $request->user();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $request->session()->put('aktif_terakhir', now()->getTimestamp());
        RiwayatLogin::catat('berhasil', $user, $request, is_string($isianAsli) ? $isianAsli : null);

        return redirect()->intended(route('beranda'))->with('success', "Selamat datang, {$user->name}.");
    }

    public function logout(Request $request)
    {
        $otomatis = $request->input('alasan') === 'tidak_aktif';
        if ($request->user()) {
            RiwayatLogin::catat($otomatis ? 'keluar_otomatis' : 'keluar', $request->user(), $request);
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $otomatis
            ? redirect()->route('login')->with('warning', self::pesanTidakAktif())
            : redirect()->route('login')->with('success', 'Anda sudah keluar.');
    }

    public static function pesanTidakAktif(): string
    {
        $menit = (int) config('dms.idle_menit');
        $lama = $menit % 60 === 0 ? ($menit / 60).' jam' : $menit.' menit';

        return "Anda keluar otomatis karena tidak aktif lebih dari {$lama}. Silakan masuk lagi.";
    }
}
