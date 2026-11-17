<?php

namespace Database\Seeders;

use App\Models\Bidang;
use App\Models\JenisKendaraan;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Data master standar (sama dengan sistem lama). Aman dijalankan berulang. */
    public function run(): void
    {
        $bidang = [
            ['kode' => 'angkutan-luar', 'nama' => 'ANGKUTAN LUAR', 'nama_tampil' => 'Angkutan Luar', 'urutan' => 1, 'input_semua_kolom' => false,
                'jenis' => ['DUMP TRUK B3', 'WING BOX', 'PICK UP BOX', 'ENGKEL BOX', 'TRONTON', 'TRAILER']],
            ['kode' => 'angkutan-dalam', 'nama' => 'ANGKUTAN DALAM', 'nama_tampil' => 'Angkutan Dalam', 'urutan' => 2, 'input_semua_kolom' => false,
                'jenis' => ['DUMP TRUK NON B3', 'FLAT']],
            ['kode' => 'alber-wil-1', 'nama' => 'ALBER WIL 1', 'nama_tampil' => 'Alat Berat Wilayah 1', 'urutan' => 3, 'input_semua_kolom' => false,
                'jenis' => ['FORKLIFT', 'WHEEL LOADER']],
            ['kode' => 'alber-wil-2', 'nama' => 'ALBER WIL 2', 'nama_tampil' => 'Alat Berat Wilayah 2', 'urutan' => 4, 'input_semua_kolom' => true,
                'jenis' => ['EXCAVATOR', 'FORKLIFT', 'WHEEL LOADER', 'BULDOZER']],
            ['kode' => 'alber-wil-3', 'nama' => 'ALBER WIL 3', 'nama_tampil' => 'Alat Berat Wilayah 3', 'urutan' => 5, 'input_semua_kolom' => true,
                'jenis' => ['FORKLIFT', 'WHEEL LOADER', 'EXCAVATOR', 'VACUM TRUK', 'OPERASIONAL CAR']],
        ];

        foreach ($bidang as $b) {
            $jenis = $b['jenis'];
            unset($b['jenis']);
            $model = Bidang::firstOrCreate(['nama' => $b['nama']], $b);
            foreach ($jenis as $j) {
                JenisKendaraan::firstOrCreate(['bidang_id' => $model->id, 'nama' => $j]);
            }
        }

        if (! User::where('role', User::ROLE_ADMIN)->exists()) {
            User::create([
                'name' => 'Administrator',
                'username' => config('dms.admin_username'),
                'password' => config('dms.admin_password'),
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ]);
            $this->command?->warn('Akun admin awal: '.config('dms.admin_username').' / '.config('dms.admin_password').' (segera ganti password!)');
        }
    }
}
