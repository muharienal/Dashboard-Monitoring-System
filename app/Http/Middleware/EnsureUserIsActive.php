<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Akun yang dinonaktifkan admin langsung keluar pada request berikutnya. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'Akun dinonaktifkan.'], 401)
                : redirect()->route('login')->with('error', 'Akun Anda nonaktif. Hubungi admin.');
        }

        return $next($request);
    }
}
