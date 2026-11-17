<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris = jam operasional 1 kendaraan pada 1 tanggal (inputan user).
 * Seluruh dashboard dihitung LANGSUNG dari tabel ini (pengganti Tableau + tabel availability_data).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sheet_data', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->foreignId('kendaraan_id')->constrained('kendaraan')->restrictOnDelete();
            // Snapshot bidang & jenis saat data diinput, supaya riwayat tetap akurat
            // walaupun kendaraan dipindah jenis/bidang di master.
            $table->foreignId('bidang_id')->constrained('bidang')->restrictOnDelete();
            $table->foreignId('jenis_kendaraan_id')->constrained('jenis_kendaraan')->restrictOnDelete();

            $table->unsignedTinyInteger('jalan')->default(0);
            $table->unsignedTinyInteger('standby')->default(24);
            $table->unsignedTinyInteger('rusak')->default(0);
            $table->unsignedTinyInteger('perbaikan')->default(0);
            $table->unsignedTinyInteger('pm')->default(0);
            $table->unsignedTinyInteger('total_jam_kerja')->default(24);

            // (jalan + standby) / 24 * 100  -> persen
            $table->decimal('availability_rate', 5, 2)->default(100);
            // Status utilitas harian unit (tepat satu bernilai 1)
            $table->unsignedTinyInteger('utilitas_jalan')->default(0);
            $table->unsignedTinyInteger('utilitas_standby')->default(1);
            $table->unsignedTinyInteger('utilitas_rusak')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['kendaraan_id', 'tanggal']);
            $table->index(['bidang_id', 'tanggal']);
            $table->index(['jenis_kendaraan_id', 'tanggal']);
            $table->index('tanggal');
            $table->index('updated_at');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            // Integritas di level database (MariaDB >= 10.2 / MySQL >= 8.0.16)
            DB::statement('ALTER TABLE sheet_data ADD CONSTRAINT chk_sheet_total_24
                CHECK (jalan + standby + rusak + perbaikan + pm = 24 AND total_jam_kerja = 24)');
            DB::statement('ALTER TABLE sheet_data ADD CONSTRAINT chk_sheet_utilitas_tunggal
                CHECK (utilitas_jalan + utilitas_standby + utilitas_rusak = 1)');

            // View kompatibilitas: bentuk "pivot" sama seperti tabel availability_data lama,
            // tapi selalu sinkron (tidak perlu trigger). Bisa dipakai bila masih ingin konek Tableau.
            DB::statement(<<<'SQL'
                CREATE OR REPLACE VIEW v_availability_data AS
                SELECT s.id AS sheet_data_id, b.nama AS bidang, j.nama AS jenis_kendaraan, k.nomor_kendaraan,
                       YEAR(s.tanggal) AS tahun, s.tanggal, s.total_jam_kerja, s.availability_rate,
                       s.utilitas_jalan, s.utilitas_standby, s.utilitas_rusak,
                       p.pivot_names,
                       CASE p.pivot_names WHEN 'JALAN' THEN s.jalan WHEN 'STANDBY' THEN s.standby
                            WHEN 'RUSAK' THEN s.rusak WHEN 'PERBAIKAN' THEN s.perbaikan ELSE s.pm END AS pivot_values
                FROM sheet_data s
                JOIN bidang b ON b.id = s.bidang_id
                JOIN jenis_kendaraan j ON j.id = s.jenis_kendaraan_id
                JOIN kendaraan k ON k.id = s.kendaraan_id
                CROSS JOIN (SELECT 'JALAN' AS pivot_names UNION ALL SELECT 'STANDBY' UNION ALL SELECT 'RUSAK'
                            UNION ALL SELECT 'PERBAIKAN' UNION ALL SELECT 'PM') p
            SQL);
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('DROP VIEW IF EXISTS v_availability_data');
        }
        Schema::dropIfExists('sheet_data');
    }
};
