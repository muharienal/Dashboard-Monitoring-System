<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Login memakai nama pengguna, bukan email.
 * Kolom users.email diganti users.username. Isinya diambil dari bagian email sebelum "@"
 * (admin@gmail.com → admin), jadi semua akun tetap bisa login dengan nama yang sama seperti sebelumnya.
 * Bila dua akun menghasilkan nama yang sama (mis. admin@gmail.com dan admin@dms.local), yang kedua menjadi "admin2".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'username')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->after('name');
        });

        $terpakai = [];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'email']) as $u) {
            $dasar = self::bersihkan(strstr((string) $u->email, '@', true) ?: (string) $u->email) ?: 'pengguna';
            $nama = $dasar;
            for ($i = 2; isset($terpakai[$nama]); $i++) {
                $nama = mb_substr($dasar, 0, 45).$i;
            }
            $terpakai[$nama] = true;
            DB::table('users')->where('id', $u->id)->update(['username' => $nama]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable(false)->change();
            $table->unique('username');
            $table->dropUnique(['email']);
            $table->dropColumn('email');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->after('name');
        });
        DB::table('users')->update(['email' => DB::raw("CONCAT(username, '@gmail.com')")]);
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->unique('email');
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }

    /** Huruf kecil, angka, titik, garis bawah, strip; maksimal 50 karakter. */
    private static function bersihkan(string $s): string
    {
        $s = preg_replace('/[^a-z0-9._-]/', '', mb_strtolower(trim($s)));

        return mb_substr(trim((string) $s, '._-'), 0, 50);
    }
};
