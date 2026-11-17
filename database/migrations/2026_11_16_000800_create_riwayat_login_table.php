<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Riwayat masuk/keluar: siapa, kapan, dari perangkat apa, termasuk percobaan yang gagal. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_login', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_nama', 100)->nullable();
            $table->string('isian', 255)->nullable();       // nama pengguna yang diketik
            $table->string('hasil', 20);                      // berhasil | gagal | nonaktif | terkunci | keluar | keluar_otomatis
            $table->string('ip', 45)->nullable();
            $table->string('perangkat', 100)->nullable();    // mis. "Chrome · Android (HP)"
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['hasil', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_login');
    }
};
