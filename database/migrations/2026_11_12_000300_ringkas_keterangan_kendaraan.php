<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Menyingkat keterangan otomatis hasil import data lama. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('kendaraan')
            ->where('keterangan', 'Dibuat otomatis dari riwayat data lama (tidak ada di master)')
            ->update(['keterangan' => 'Dari data lama']);
    }

    public function down(): void
    {
        //
    }
};
