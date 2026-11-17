<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat perubahan data master: kendaraan, jenis, bidang, dan akun.
 * Nama objek & pengguna disimpan sebagai salinan agar tetap terbaca setelah datanya dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_master', function (Blueprint $table) {
            $table->id();
            $table->string('objek', 20);           // kendaraan | jenis | bidang | akun
            $table->unsignedBigInteger('objek_id')->nullable();
            $table->string('objek_nama', 150);
            $table->string('aksi', 20);            // tambah | ubah | hapus | nonaktif | aktif | password | keluar
            $table->json('perubahan')->nullable(); // [{kolom,label,dari,ke}]
            $table->string('catatan', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_nama', 100)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['objek', 'created_at']);
            $table->index('created_at');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_master');
    }
};
