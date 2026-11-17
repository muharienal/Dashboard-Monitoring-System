<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat perubahan data input: siapa mengubah angka apa, dari berapa ke berapa, dan kapan.
 * Nama pengguna & nomor kendaraan disimpan sebagai salinan, supaya riwayat tetap terbaca
 * walaupun akun dihapus atau kendaraan diganti nomornya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_input', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sheet_data_id')->nullable()->constrained('sheet_data')->nullOnDelete();
            $table->date('tanggal');
            $table->foreignId('kendaraan_id')->nullable()->constrained('kendaraan')->nullOnDelete();
            $table->string('nomor_kendaraan', 50);
            $table->foreignId('bidang_id')->nullable()->constrained('bidang')->nullOnDelete();
            $table->string('aksi', 10); // buat | ubah
            $table->json('sebelum')->nullable();
            $table->json('sesudah');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_nama', 100)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['bidang_id', 'tanggal']);
            $table->index(['kendaraan_id', 'tanggal']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_input');
    }
};
