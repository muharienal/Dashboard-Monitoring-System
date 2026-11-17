# DMS v2 – Dashboard Management System (Laravel)

**PT Petrokopindo Cipta Selaras** · migrasi dari PHP native + Tableau Public ke **Laravel 11** dengan dashboard realtime bawaan.

- Tanpa npm / Node.js. Semua aset (Bootstrap, Font Awesome, Chart.js, SheetJS, font Inria Sans) sudah ada di `public/assets/vendor`, jadi aplikasi jalan walau server **tanpa internet**.
- Folder `vendor/` (library PHP) sudah disertakan. `composer install` hanya perlu dijalankan kalau ingin memperbarui library.
- Dashboard dihitung **langsung dari tabel `sheet_data`** (data inputan user) dan otomatis diperbarui ±15 detik setelah ada input baru. Tidak perlu Tableau, extract, atau publish ulang.

---

## 1. Kebutuhan server

| Komponen | Versi |
|---|---|
| PHP | **8.2** atau lebih baru (server lama: `E:\php-8.2.x` ✔) |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `intl` (disarankan) |
| Database | MariaDB ≥ 10.4 (server lama 10.4.32 ✔) atau MySQL ≥ 8.0.16 |
| Web server | IIS (sudah ada `public/web.config`) atau Apache/XAMPP (sudah ada `public/.htaccess`) |

## 2. Instalasi

```bash
# 1. Salin folder proyek ke server, mis. E:\www\dms
# 2. Buat file konfigurasi
composer install
copy .env.example .env          # (Linux: cp .env.example .env)
php artisan key:generate

# 3. Edit .env → isi DB_DATABASE / DB_USERNAME / DB_PASSWORD (database BARU, mis. dms_laravel)
#    dan APP_URL (mis. http://dms.petrokopindo.com)

# 4. Buat database kosong, lalu buat tabel
php artisan migrate --force

# 5a. Data dari sistem lama (disarankan) → lihat bagian 3
# 5b. Atau instalasi bersih (data master + admin awal):
php artisan db:seed --force     # admin awal: admin / Admin#12345 (segera ganti)

# 6. Optimasi untuk produksi
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**IIS:** arahkan *Physical Path* site ke folder `public\` (bukan root proyek). Pastikan modul **URL Rewrite** terpasang. Beri user IIS (`IIS_IUSRS`) hak tulis ke `storage\` dan `bootstrap\cache\`.

**Apache/XAMPP:** arahkan `DocumentRoot` ke folder `public/`.

## 3. Migrasi data dari sistem lama

Database lama (`db_dashboard`) **tidak diubah sama sekali**. Data dibaca lalu disalin ke database baru.

```bash
# .env: isi LEGACY_DB_DATABASE=db_dashboard (+ user/password bila berbeda)
php artisan dms:import-legacy --with-master-data
```

Perintah aman diulang (idempotent). Hasil uji dengan dump `db_dashboard.sql` (11 Nov 2026):

| Keterangan | Jumlah |
|---|---|
| Baris `sheet_data` diimpor | 316.167 |
| Baris diperbaiki (jam negatif, standby 25/26/140, total ≠ 24) | 20 |
| Status utilitas dihitung ulang dengan rumus yang berlaku | 6.923 (mayoritas data 2024, rumus lama) |
| Kendaraan di riwayat tapi sudah tidak ada di master → dibuat **non-aktif** | 102 |
| Baris `master_data` (form lama Jan 2025) yang belum ada di `sheet_data` | 8 |
| Akun pengguna (password lama tetap berlaku) | 7 |

Opsi: `--keep-utilitas` (pertahankan status utilitas lama), `--skip-users`.

> **Catatan:** dump yang diterima terpotong di bagian paling akhir (tabel `user_sessions` dan definisi index). Tidak berpengaruh: sesi login sekarang dikelola Laravel, dan seluruh index/constraint didefinisikan ulang di migration.

## 4. Peta halaman lama → baru

URL lama otomatis dialihkan (redirect 301), jadi bookmark pengguna tetap jalan.

| Lama (PHP native) | Baru (Laravel) | Isi |
|---|---|---|
| `login.php`, `logout.php` | `/login`, menu **Keluar** | Login email + password |
| — | `/` **Beranda** | Ringkasan semua bidang + progres input hari ini |
| `sheetAnglu / sheetAngdal / sheetAlberWil1pg / sheetAlberWil2pg / sheetAlberWil2fi` | `/input/{bidang}` **Input Data** | Form input harian |
| `dashboardAnglu / Angdal / AlberWil1/2/3` | `/dashboard/summary/{bidang}` **Dashboard Bidang** | Availability, utilisasi, unit per bulan/hari, perbaikan terbanyak |
| `availability.php` | `/dashboard/availability` **Availability** | Komposisi jam & availability per jenis |
| `utilisasi.php` | `/dashboard/utilisasi` **Utilisasi** | Rata-rata unit jalan/standby/rusak per hari, per jenis |
| `maintenance.php` | `/dashboard/maintenance` **Perbaikan** | Jam perbaikan per kendaraan per bidang |
| `reports.php` + `GeneralReports.php` | `/dashboard/laporan` **Laporan** (tab Rekap / Detail per kendaraan) | Rekap per bidang & jenis, dan tabel jam per kendaraan per tanggal |
| `masterKendaraan.php` | `/master/kendaraan` **Kendaraan** | Kendaraan, jenis, pengaturan bidang |
| `account.php` | `/akun` **Akun** | Kelola akun, status online, paksa keluar |
| — | `/profil` **Ganti Password** | |

Semua dashboard punya filter Tahun / Bulan / Tanggal (dan Bidang / Jenis bila relevan), tombol **Excel**, dan indikator **Live**.

## Siklus kendaraan (aktif, nonaktif, pindah, ganti nomor)

| Situasi | Yang terjadi |
|---|---|
| Kendaraan baru | Diisi **Mulai beroperasi**. Kendaraan muncul di form input mulai tanggal itu, tidak muncul di tanggal sebelumnya (mencegah data "standby" palsu). |
| Dinonaktifkan (dijual, rusak berat, dll.) | Pilih **Nonaktif mulai** (maks. hari ini) + alasan. Mulai tanggal itu kendaraan hilang dari form input. Tanggal sebelumnya masih bisa diisi bila ada yang terlewat. **Seluruh riwayat tetap ada** di dashboard, laporan, dan Excel. |
| Diaktifkan kembali | Tombol **Aktifkan**. Kendaraan muncul lagi di form input. |
| Pindah jenis/bidang | Ubah di halaman Kendaraan. Riwayat lama tetap tercatat di bidang/jenis saat data diinput. |
| Ganti nomor (plat baru) | Ubah nomor. Riwayat ikut memakai nomor baru (relasi ID, bukan teks). |
| Hapus | Hanya bisa bila belum punya riwayat sama sekali. |

Data lama: tanggal mulai = data pertama, tanggal nonaktif = sehari setelah data terakhir (diisi otomatis oleh `php artisan migrate`).

## 5. Rumus (padanan rumus Tableau)

Semua rumus per baris ada di satu tempat: `app/Support/JamKerja.php`. Rumus agregat ada di `app/Services/DashboardService.php`.

| Rumus Tableau | Implementasi Laravel |
|---|---|
| `AR JALAN + STANDBY = SUM(cal_jalan)+SUM(cal_standby)` | `SUM(jalan)+SUM(standby)` |
| `AR PERBAIKAN+RUSAK+PM = SUM(cal_perbaikan)+SUM(cal_rusak)+SUM(cal_pm)+SUM(cal_jalan)+SUM(cal_standby)` | total jam semua status |
| `AVAILABILITY RATE FIX = (AR JALAN+STANDBY / AR PERBAIKAN+RUSAK+PM) * 100` | sama persis |
| `SUM([utilitas_x])/COUNTD([tanggal])` (bar & trend utilitas) | rata-rata unit berstatus x per hari |
| `SUM([perbaikan])/COUNTD([tanggal])` (warna Maintenance) | "rata-rata jam/hari" di tooltip |
| `SUM(amount)`, `SUM(utilitas_*)` (Utilitas Unit) | unit-hari dan jumlah per status |
| Pie / Detail Report `SUM(pivot_values)` per `pivot_names` | dihitung langsung dari 5 kolom jam |
| Parameter `Top N = 30` | `DMS_TOP_N_MAINTENANCE=30` di `.env` |

Rumus per baris (dulu di JavaScript tiap halaman, sekarang dihitung ulang di **server**):

```
standby           = 24 − (jalan + rusak + perbaikan + pm)
total_jam_kerja   = 24
availability_rate = (jalan + standby) / 24 × 100
status utilitas   = JALAN   bila (jalan > 4 dan rusak = perbaikan = pm = 0) atau jalan nilai terbesar
                    STANDBY bila bukan JALAN dan standby nilai terbesar
                    RUSAK   selain itu
```

**Verifikasi:** angka dashboard baru dibandingkan dengan rumus Tableau di database lama.
- Oktober 2026, Angkutan Luar & Alber Wil 1 → **identik** (AR 99,92% / 99,21%, utilitas & maintenance sama).
- Agustus 2025, Alber Wil 1 → Tableau lama **97,22%**, yang benar **97,04%**. Tableau menghitung 5.904 jam "hantu" dari 1.230 baris `availability_data` yatim (lihat bug #1).

## 6. Bug & masalah sistem lama yang diperbaiki

**Keamanan**
1. `sheetSave.php`, `getDataByDate.php`, `koneksiKendaraan.php`, `koneksiBidang.php`, `search.php` **bisa diakses tanpa login**. Siapa pun bisa membaca, menulis, bahkan menghapus data kendaraan.
2. `aksiAddUser.php` / `aksiEditUser.php` / `aksiHapusUser.php` tidak mengecek role. User non-admin (bahkan tanpa login) bisa membuat atau menghapus akun. Hapus akun via GET tanpa CSRF.
3. `search.php` rentan **SQL injection** (`LIKE '%$search%'`).
4. XSS di `account.php` (nama/email dicetak tanpa escape).
5. Kredensial database ditulis ulang di 5 file. Sekarang hanya di `.env`.
6. Tidak ada CSRF di semua form. Sekarang semua form & AJAX memakai token CSRF.
7. Tidak ada pembatasan percobaan login. Sekarang 5x/menit per email+IP.

**Logika & data**
1. Trigger `after_insert/after_update` mengisi `availability_data`, tapi baris yang dihapus tidak ikut terhapus → **1.540 baris yatim** membuat angka Tableau dobel. Tabel pivot dihapus; dashboard dihitung langsung (tersedia view `v_availability_data` bila masih ingin konek Tableau).
2. Validasi hanya di browser → ada data **jam negatif** (`jalan -116, standby 140`), total ≠ 24. Sekarang divalidasi di server + **CHECK constraint** di database.
3. Simpan mengirim **seluruh baris** → user Pemeliharaan yang menyimpan setelah user Bidang **menimpa jam jalan** dengan nilai lama (begitu juga sebaliknya). Sekarang hanya kolom yang boleh diedit user yang dipakai. Bila dua orang mengubah sel yang sama, muncul peringatan konflik (perubahan tidak hilang).
4. Hak edit kolom ditentukan dari **ID user yang di-hardcode** (`allowedJalan = [1,2,3,4,5]`, `allowedPm = [1,6]`) dan hanya di JavaScript. Sekarang berdasarkan role, dicek di server, dan bisa diatur per bidang ("Semua kolom terbuka", default aktif untuk Alber Wil 2 & 3 sesuai perilaku lama).
5. Halaman Alber Wil 3 (`sheetAlberWil2fi`) sama sekali tanpa pengecekan hak edit. Sekarang mengikuti aturan yang sama.
6. Rumus status utilitas pernah berubah → 6.923 baris (2024) memakai rumus berbeda. Sekarang dihitung ulang dengan satu rumus.
7. Hapus kendaraan di master membuat riwayatnya "yatim" (nama tidak ada lagi). Kendaraan kini bisa **dinonaktifkan**; kendaraan dengan riwayat tidak bisa dihapus. Ganti nomor kendaraan otomatis berlaku ke riwayat (relasi ID, bukan teks).
8. Riwayat menyimpan snapshot bidang & jenis, jadi data tetap akurat walau kendaraan dipindah bidang.
9. Login role **Manajemen** diarahkan ke halaman input (tidak punya akses). Sekarang semua role ke Beranda.
10. Tombol hapus di Manajemen Akun mengarah ke `delete_user.php` yang **tidak ada**.
11. Status Online dihitung dari ada/tidaknya baris `user_sessions`, yang tidak pernah kedaluwarsa. Sekarang dari aktivitas sesi nyata (lifetime 480 menit).
12. Tableau *Maintenance* memfilter nama bidang lama (`ALBER WIL 1 PG`, `ALBER WIL 2 FI`, …) yang sudah tidak dipakai. Chart *AR Operasional Car* ikut memasukkan *WHEEL LOADER* (dihitung dua kali). Keduanya diperbaiki.
13. Input tanggal di masa depan tidak dicegah. Sekarang ditolak.
14. `updated_at` lama tersimpan UTC. Saat impor dikonversi ke WIB.

**Tampilan**
- Satu layout Blade untuk semua halaman (sebelumnya sidebar disalin di 20+ file dan tidak konsisten: ada menu General Reports yang aktif di sebagian halaman saja).
- Responsif untuk HP (menu geser), navigasi keyboard di form input (Enter/↑/↓, Ctrl+S), baris belum tersimpan ditandai, ringkasan total & availability per hari di bawah grid, peringatan sebelum meninggalkan halaman dengan perubahan belum disimpan.
- Warna status mengikuti palet Tableau, dengan dua penyesuaian agar lolos uji buta warna: **STANDBY** sedikit lebih gelap (`#86c46f`), dan **PM sekarang biru** (`#3b74c4`) karena kuning PM lama hampir sama dengan oranye PERBAIKAN. Bisa diubah di `config/dms.php`.

## 7. Perubahan perilaku yang perlu diketahui

- **Trend Lines** di Dashboard Summary: bila satu bulan dipilih → trend **harian**; bila "Semua Bulan" → trend bulanan (di Tableau sumbu bulan menghasilkan satu titik saja ketika satu bulan dipilih).
- **Laporan → Detail per kendaraan**: bila bulan dipilih → kolom per tanggal; bila "Semua Bulan" → kolom per bulan. Di Tableau kolom "hari" menjumlahkan tanggal yang sama dari bulan berbeda.
- **Perbaikan** (dulu Maintenance) hanya menampilkan kendaraan dengan jam perbaikan > 0, diurutkan dari terbesar.
- **Utilisasi** ditampilkan sebagai satu angka persen (unit berstatus jalan ÷ seluruh unit per hari), selain rata-rata unit per hari.
- Workbook Alber Wil 2 memakai `ROUND()` pada utilitas, sedangkan bidang lain tidak. Sekarang seragam: tidak dibulatkan, ditampilkan 2 desimal.
- Popup "Panduan Penggunaan" hanya tampil otomatis pada kunjungan pertama (bisa dibuka lagi lewat tombol Bantuan).

## 8. Hak akses

| Role | Dashboard | Input | Kolom yang bisa diisi | Master & Akun |
|---|---|---|---|---|
| Admin | ✔ | semua bidang | semua | ✔ |
| Bidang | ✔ | semua bidang, atau dibatasi ke satu bidang (opsional) | Jalan, Rusak (+ Perbaikan, PM bila bidang "Semua kolom terbuka") | ✘ |
| Pemeliharaan | ✔ | semua bidang | Perbaikan, PM (+ Jalan, Rusak bila "Semua kolom terbuka") | ✘ |
| Manajemen | ✔ | ✘ | – | ✘ |

## 9. Struktur database

| Tabel | Isi |
|---|---|
| `bidang` | 5 bidang, kode URL, nama tampilan, urutan, opsi `input_semua_kolom` |
| `jenis_kendaraan` | jenis per bidang (unik per bidang) |
| `kendaraan` | nomor per jenis (unik), `is_active`, keterangan |
| `sheet_data` | 1 baris = 1 kendaraan × 1 tanggal. FK kendaraan/bidang/jenis, 5 kolom jam, hasil hitung, `created_by`/`updated_by`. Unik `(kendaraan_id, tanggal)`, CHECK total = 24 |
| `users`, `sessions` | akun & sesi login (status online, paksa logout) |
| `cache` | cache aplikasi |
| view `v_availability_data` | format pivot seperti tabel lama (opsional, untuk Tableau/BI eksternal) |

## 10. Pengujian

```bash
php vendor/bin/phpunit      # 39 test: rumus, hak akses, konflik simpan, angka dashboard
```

## 11. Konfigurasi `.env` khusus DMS

| Variabel | Default | Keterangan |
|---|---|---|
| `DMS_REFRESH_DETIK` | 15 | Interval cek data baru (realtime) |
| `DMS_TOP_N_MAINTENANCE` | 30 | Jumlah bar Maintenance di Dashboard Summary |
| `SESSION_LIFETIME` | 480 | Menit sampai sesi idle berakhir |
| `LEGACY_DB_*` | – | Database lama, hanya untuk `dms:import-legacy` |

## 12. Struktur kode

```
app/Support/JamKerja.php            rumus per baris (satu sumber kebenaran)
app/Support/Filter.php              filter Tahun/Bulan/Tanggal/Bidang/Jenis → rentang tanggal ber-index
app/Services/DashboardService.php   semua query dashboard (pengganti Tableau)
app/Services/InputService.php       simpan input: validasi, hak kolom, merge, deteksi konflik
app/Console/Commands/ImportLegacy.php   migrasi data lama
resources/views/                    Blade (layout, input, dashboard, master, akun)
public/assets/js/                   app.js (helper), input.js, dashboards.js
```
