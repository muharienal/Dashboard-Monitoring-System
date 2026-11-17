<?php

return [
    'nama_aplikasi' => env('DMS_NAMA', 'Dashboard Management System'),
    // Nama singkat aplikasi di judul tab, logo, footer, dan semua teks.
    'nama_singkat' => 'DMS v2',
    // Tahun pada footer: "DMS v2 - IT PT Petrokopindo Cipta Selaras © 2026"
    'tahun_hak_cipta' => env('DMS_TAHUN_HAK_CIPTA', '2026'),
    // Akun admin awal (hanya dipakai seeder / impor data lama)
    'admin_username' => env('DMS_ADMIN_USERNAME', 'admin'),
    'admin_password' => env('DMS_ADMIN_PASSWORD', 'Admin#12345'),
    'perusahaan' => env('DMS_PERUSAHAAN', 'PT Petrokopindo Cipta Selaras'),
    // Interval polling realtime dashboard (detik)
    'refresh_detik' => (int) env('DMS_REFRESH_DETIK', 15),
    // Jumlah kendaraan teratas pada grafik Maintenance di Dashboard Summary (parameter "Top N" Tableau)
    'top_n_maintenance' => (int) env('DMS_TOP_N_MAINTENANCE', 30),
    // Keluar otomatis bila tidak ada aktivitas (mouse/keyboard/sentuh) selama N menit. 0 = mati.
    'idle_menit' => (int) env('DMS_IDLE_MENIT', 120),
    'kms_url' => env('DMS_KMS_URL', 'https://kms.petrokopindo.com/'),
    // Warna status: diturunkan dari palet workbook Tableau, disesuaikan agar lolos uji buta warna
    // (PM dipindah ke biru karena kuning-oranye lama tidak bisa dibedakan dari PERBAIKAN).
    'warna' => [
        'JALAN' => '#2b7c40',
        'STANDBY' => '#86c46f',
        'RUSAK' => '#ae123a',
        'PERBAIKAN' => '#e8821f',
        'PM' => '#3b74c4',
        'util_jalan' => '#3f8f47',
        'util_standby' => '#d9a520',
        'util_rusak' => '#d3293d',
    ],
];
