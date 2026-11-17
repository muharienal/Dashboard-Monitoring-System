<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mencegah error SQL ketika kode sudah diperbarui tetapi "php artisan migrate" belum dijalankan.
 * Menampilkan pesan yang jelas, bukan pesan SQL mentah.
 */
class EnsureDatabaseIsMigrated
{
    public function handle(Request $request, Closure $next): Response
    {
        $pending = $this->pending();
        if ($pending) {
            $pesan = 'Database belum diperbarui. Admin perlu menjalankan "php artisan migrate" di server.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $pesan, 'pending' => $pending], 503);
            }

            return response()->view('errors.migrasi', ['pending' => $pending], 503);
        }

        return $next($request);
    }

    /** @return string[] nama migration yang belum dijalankan */
    private function pending(): array
    {
        try {
            $migrator = app('migrator');
            $files = array_keys($migrator->getMigrationFiles([database_path('migrations')]));
            if (! $migrator->repositoryExists()) {
                return $files;
            }

            return array_values(array_diff($files, $migrator->getRepository()->getRan()));
        } catch (\Throwable) {
            return []; // koneksi DB gagal: biarkan error koneksi ditangani normal
        }
    }
}
