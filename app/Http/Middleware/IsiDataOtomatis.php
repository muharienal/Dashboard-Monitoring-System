<?php

namespace App\Http\Middleware;

use App\Support\IsiOtomatis;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Menjalankan pengisian data harian otomatis (Standby 24 jam) saat pengguna yang login membuka DMS. */
class IsiDataOtomatis
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            IsiOtomatis::pastikan();
        }

        return $next($request);
    }
}
