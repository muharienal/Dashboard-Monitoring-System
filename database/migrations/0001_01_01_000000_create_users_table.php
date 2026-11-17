<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bidang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();           // slug URL, mis. "angkutan-luar"
            $table->string('nama', 100)->unique();          // nama data, mis. "ANGKUTAN LUAR"
            $table->string('nama_tampil', 100);             // label UI, mis. "Angkutan Luar"
            $table->unsignedSmallInteger('urutan')->default(0);
            // true  = semua role input boleh mengisi semua kolom (perilaku lama Alber Wil 2 & 3)
            // false = kolom dibatasi per role (bidang: jalan & rusak, pemeliharaan: perbaikan & pm)
            $table->boolean('input_semua_kolom')->default(false);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['admin', 'bidang', 'pemeliharaan', 'manajemen']);
            // Opsional: membatasi user role "bidang" hanya ke satu bidang. NULL = semua bidang.
            $table->foreignId('bidang_id')->nullable()->constrained('bidang')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('bidang');
    }
};
