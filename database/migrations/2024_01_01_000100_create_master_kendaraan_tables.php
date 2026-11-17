<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_kendaraan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bidang_id')->constrained('bidang')->restrictOnDelete();
            $table->string('nama', 100);
            $table->timestamps();
            $table->unique(['bidang_id', 'nama']);
        });

        Schema::create('kendaraan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_kendaraan_id')->constrained('jenis_kendaraan')->restrictOnDelete();
            $table->string('nomor_kendaraan', 50);
            // Kendaraan non-aktif tidak muncul di form input, tapi riwayatnya tetap ada di dashboard.
            $table->boolean('is_active')->default(true);
            $table->string('keterangan')->nullable();
            $table->timestamps();
            $table->unique(['jenis_kendaraan_id', 'nomor_kendaraan']);
            $table->index('nomor_kendaraan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kendaraan');
        Schema::dropIfExists('jenis_kendaraan');
    }
};
