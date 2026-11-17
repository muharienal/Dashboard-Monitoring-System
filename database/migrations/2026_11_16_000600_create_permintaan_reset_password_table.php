<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Permintaan "Lupa password" dari halaman login, ditangani oleh admin. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permintaan_reset_password', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('catatan', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('status', 10)->default('menunggu'); // menunggu | selesai | ditolak
            $table->foreignId('ditangani_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ditangani_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permintaan_reset_password');
    }
};
