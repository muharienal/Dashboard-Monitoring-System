<?php

use App\Models\Kendaraan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Periode aktif kendaraan:
 *  - aktif_sejak    : tanggal mulai beroperasi. Sebelum tanggal ini kendaraan tidak muncul di form input.
 *  - nonaktif_sejak : tanggal mulai nonaktif. Mulai tanggal ini kendaraan tidak muncul di form input,
 *                     tetapi riwayat sebelumnya tetap ada di dashboard & laporan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kendaraan', function (Blueprint $table) {
            $table->date('aktif_sejak')->nullable()->after('is_active');
            $table->date('nonaktif_sejak')->nullable()->after('aktif_sejak');
        });

        Kendaraan::isiPeriodeAktifKosong();
    }

    public function down(): void
    {
        Schema::table('kendaraan', function (Blueprint $table) {
            $table->dropColumn(['aktif_sejak', 'nonaktif_sejak']);
        });
    }
};
