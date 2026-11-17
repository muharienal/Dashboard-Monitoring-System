<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data harian otomatis: setiap kendaraan aktif otomatis punya data Standby 24 jam per hari,
 * jadi petugas cukup mengubah yang Jalan / Rusak / Perbaikan / PM.
 *  - sheet_data.otomatis = 1 → baris dibuat sistem dan belum pernah disimpan petugas.
 *  - pengaturan.isi_otomatis_sejak → tanggal mulai pengisian otomatis (awal bulan saat migrasi);
 *    tanggal sebelumnya tidak diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sheet_data', function (Blueprint $table) {
            $table->boolean('otomatis')->default(false)->after('utilitas_rusak');
            $table->index(['bidang_id', 'tanggal', 'otomatis']);
        });

        Schema::create('pengaturan', function (Blueprint $table) {
            $table->string('kunci', 60)->primary();
            $table->string('nilai')->nullable();
            $table->timestamps();
        });
        DB::table('pengaturan')->insert([
            'kunci' => 'isi_otomatis_sejak',
            'nilai' => now()->startOfMonth()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('sheet_data')->where('otomatis', true)->delete();
        Schema::table('sheet_data', function (Blueprint $table) {
            $table->dropIndex(['bidang_id', 'tanggal', 'otomatis']);
            $table->dropColumn('otomatis');
        });
        Schema::dropIfExists('pengaturan');
    }
};
