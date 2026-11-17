-- =====================================================================================
--  SINKRON DATA DMS LAMA (db_dashboard)  ->  DMS v2 (dms_laravel)
-- =====================================================================================
--  Kapan dipakai : setiap kali ingin menyalin data terbaru dari DMS lama ke DMS v2.
--  Aman diulang  : data yang sama diperbarui (tidak dobel), data baru ditambahkan.
--
--  Cara pakai (phpMyAdmin):
--    1. Pastikan database DMS lama (db_dashboard) dan dms_laravel ada di server MySQL/MariaDB yang sama.
--       Bila DMS lama ada di server lain: export db_dashboard di sana, lalu impor ke server ini dengan nama db_dashboard.
--    2. Bila nama database berbeda, ganti semua tulisan `db_dashboard` / `dms_laravel` di file ini
--       (Ctrl+H di Notepad/VS Code) sesuai nama yang dipakai.
--    3. phpMyAdmin > tab SQL (atau Import) > jalankan seluruh isi file ini.
--    4. Hasil akhir: tabel ringkasan + daftar kendaraan yang perlu dicek.
--
--  Yang dilakukan (sama dengan perintah: php artisan dms:import-legacy):
--    - Nama bidang lama disamakan: ALBER WIL 1 PG -> ALBER WIL 1, ALBER WIL 2 PG -> ALBER WIL 2, ALBER WIL 2 FI -> ALBER WIL 3.
--    - Jenis & kendaraan baru ditambahkan. Kendaraan yang hanya ada di riwayat dibuat NON-AKTIF ("Dari data lama").
--    - Jam negatif / lebih dari 24 dipotong ke 0-24; Standby dihitung ulang = 24 - (Jalan + Rusak + Perbaikan + PM).
--    - Availability & status utilitas dihitung ulang dengan rumus yang sama dengan form input DMS v2.
--    - Baris yang total jamnya tetap lebih dari 24 dilewati.
--    - Akun baru dari DMS lama ditambahkan (password lama). Akun yang sudah ada di DMS v2 TIDAK diubah.
--    - Waktu updated_at lama (UTC) diubah ke WIB (+7 jam).
--  Tidak dihapus: data yang dihapus di DMS lama tetap ada di DMS v2.
-- =====================================================================================

SET NAMES utf8mb4;
START TRANSACTION;

-- 1. Bidang baru (biasanya tidak ada; 5 bidang standar sudah ada di DMS v2) ------------------
INSERT IGNORE INTO `dms_laravel`.`bidang` (`kode`, `nama`, `nama_tampil`, `urutan`, `input_semua_kolom`, `created_at`, `updated_at`)
SELECT LOWER(REPLACE(x.nama, ' ', '-')), x.nama, CONCAT(UPPER(LEFT(x.nama, 1)), LOWER(SUBSTRING(x.nama, 2))), 99, 0, NOW(), NOW()
FROM (
    SELECT DISTINCT CASE TRIM(b.nama_bidang)
        WHEN 'ALBER WIL 1 PG' THEN 'ALBER WIL 1' WHEN 'ALBER WIL 2 PG' THEN 'ALBER WIL 2' WHEN 'ALBER WIL 2 FI' THEN 'ALBER WIL 3'
        ELSE TRIM(b.nama_bidang) END COLLATE utf8mb4_unicode_ci AS nama
    FROM `db_dashboard`.`bidang` b
) x
WHERE NOT EXISTS (SELECT 1 FROM `dms_laravel`.`bidang` n WHERE n.nama = x.nama);

-- 2. Jenis kendaraan (dari master lama + yang muncul di riwayat) ------------------------------
INSERT IGNORE INTO `dms_laravel`.`jenis_kendaraan` (`bidang_id`, `nama`, `created_at`, `updated_at`)
SELECT DISTINCT nb.id, x.jenis, NOW(), NOW()
FROM (
    SELECT CASE TRIM(b.nama_bidang) WHEN 'ALBER WIL 1 PG' THEN 'ALBER WIL 1' WHEN 'ALBER WIL 2 PG' THEN 'ALBER WIL 2' WHEN 'ALBER WIL 2 FI' THEN 'ALBER WIL 3'
               ELSE TRIM(b.nama_bidang) END COLLATE utf8mb4_unicode_ci AS bidang,
           TRIM(j.nama_jenis) COLLATE utf8mb4_unicode_ci AS jenis
    FROM `db_dashboard`.`jenis_kendaraan` j JOIN `db_dashboard`.`bidang` b ON b.id_bidang = j.id_bidang
    UNION
    SELECT DISTINCT CASE TRIM(s.bidang) WHEN 'ALBER WIL 1 PG' THEN 'ALBER WIL 1' WHEN 'ALBER WIL 2 PG' THEN 'ALBER WIL 2' WHEN 'ALBER WIL 2 FI' THEN 'ALBER WIL 3'
               ELSE TRIM(s.bidang) END COLLATE utf8mb4_unicode_ci,
           TRIM(s.jenis_kendaraan) COLLATE utf8mb4_unicode_ci
    FROM `db_dashboard`.`sheet_data` s
) x
JOIN `dms_laravel`.`bidang` nb ON nb.nama = x.bidang;

-- 3a. Kendaraan di master lama (aktif) ---------------------------------------------------------
INSERT IGNORE INTO `dms_laravel`.`kendaraan` (`jenis_kendaraan_id`, `nomor_kendaraan`, `is_active`, `keterangan`, `created_at`, `updated_at`)
SELECT DISTINCT nj.id, TRIM(k.nomor_kendaraan) COLLATE utf8mb4_unicode_ci, 1, NULL, NOW(), NOW()
FROM `db_dashboard`.`kendaraan` k
JOIN `db_dashboard`.`jenis_kendaraan` j ON j.id_jenis = k.id_jenis
JOIN `db_dashboard`.`bidang` b ON b.id_bidang = j.id_bidang
JOIN `dms_laravel`.`bidang` nb ON nb.nama = (CASE TRIM(b.nama_bidang) WHEN 'ALBER WIL 1 PG' THEN 'ALBER WIL 1' WHEN 'ALBER WIL 2 PG' THEN 'ALBER WIL 2'
                                                WHEN 'ALBER WIL 2 FI' THEN 'ALBER WIL 3' ELSE TRIM(b.nama_bidang) END COLLATE utf8mb4_unicode_ci)
JOIN `dms_laravel`.`jenis_kendaraan` nj ON nj.bidang_id = nb.id AND nj.nama = TRIM(j.nama_jenis) COLLATE utf8mb4_unicode_ci;

-- 3b. Kendaraan yang hanya ada di riwayat (sudah dihapus dari master lama): non-aktif --------
INSERT IGNORE INTO `dms_laravel`.`kendaraan` (`jenis_kendaraan_id`, `nomor_kendaraan`, `is_active`, `keterangan`, `created_at`, `updated_at`)
SELECT DISTINCT nj.id, TRIM(s.nomor_kendaraan) COLLATE utf8mb4_unicode_ci, 0, 'Dari data lama', NOW(), NOW()
FROM `db_dashboard`.`sheet_data` s
JOIN `dms_laravel`.`bidang` nb ON nb.nama = (CASE TRIM(s.bidang) WHEN 'ALBER WIL 1 PG' THEN 'ALBER WIL 1' WHEN 'ALBER WIL 2 PG' THEN 'ALBER WIL 2'
                                                WHEN 'ALBER WIL 2 FI' THEN 'ALBER WIL 3' ELSE TRIM(s.bidang) END COLLATE utf8mb4_unicode_ci)
JOIN `dms_laravel`.`jenis_kendaraan` nj ON nj.bidang_id = nb.id AND nj.nama = TRIM(s.jenis_kendaraan) COLLATE utf8mb4_unicode_ci;

-- 4. Akun baru (nama pengguna = bagian email sebelum "@"); akun yang sudah ada tidak diubah ---
INSERT IGNORE INTO `dms_laravel`.`users` (`name`, `username`, `password`, `role`, `is_active`, `created_at`, `updated_at`)
SELECT u.username, LOWER(SUBSTRING_INDEX(TRIM(u.email), '@', 1)) COLLATE utf8mb4_unicode_ci, u.password,
       IF(u.role IN ('admin', 'bidang', 'pemeliharaan', 'manajemen'), u.role, 'manajemen'), 1, COALESCE(u.created_at, NOW()), NOW()
FROM `db_dashboard`.`users` u;

-- 5. Data harian: hitung ulang dengan rumus DMS v2, lalu tambah / perbarui ---------------------
INSERT INTO `dms_laravel`.`sheet_data`
    (`tanggal`, `kendaraan_id`, `bidang_id`, `jenis_kendaraan_id`, `jalan`, `standby`, `rusak`, `perbaikan`, `pm`, `total_jam_kerja`,
     `availability_rate`, `utilitas_jalan`, `utilitas_standby`, `utilitas_rusak`, `created_by`, `updated_by`, `created_at`, `updated_at`)
SELECT h.tanggal, h.kendaraan_id, h.bidang_id, h.jenis_id, h.j, h.sb, h.r, h.p, h.m, 24,
       ROUND((h.j + h.sb) / 24 * 100, 2),
       h.uj,
       IF(h.uj = 0 AND (h.mx = 0 OR h.sb = h.mx), 1, 0),
       IF(h.uj = 0 AND NOT (h.mx = 0 OR h.sb = h.mx), 1, 0),
       NULL, NULL, h.waktu, h.waktu
FROM (
    SELECT g.*,
           IF((g.j > 4 AND g.r = 0 AND g.p = 0 AND g.m = 0) OR (g.j = g.mx AND g.mx > 0), 1, 0) AS uj
    FROM (
        SELECT c.*, GREATEST(c.j, c.sb, c.r, c.p, c.m) AS mx
        FROM (
            SELECT s.id, s.tanggal, nk.id AS kendaraan_id, nb.id AS bidang_id, nj.id AS jenis_id,
                   LEAST(GREATEST(s.jalan, 0), 24) AS j,
                   LEAST(GREATEST(s.rusak, 0), 24) AS r,
                   LEAST(GREATEST(s.perbaikan, 0), 24) AS p,
                   LEAST(GREATEST(s.pm, 0), 24) AS m,
                   24 - (LEAST(GREATEST(s.jalan, 0), 24) + LEAST(GREATEST(s.rusak, 0), 24) + LEAST(GREATEST(s.perbaikan, 0), 24) + LEAST(GREATEST(s.pm, 0), 24)) AS sb,
                   COALESCE(s.updated_at + INTERVAL 7 HOUR, NOW()) AS waktu
            FROM `db_dashboard`.`sheet_data` s
            JOIN `dms_laravel`.`bidang` nb ON nb.nama = (CASE TRIM(s.bidang) WHEN 'ALBER WIL 1 PG' THEN 'ALBER WIL 1' WHEN 'ALBER WIL 2 PG' THEN 'ALBER WIL 2'
                                                            WHEN 'ALBER WIL 2 FI' THEN 'ALBER WIL 3' ELSE TRIM(s.bidang) END COLLATE utf8mb4_unicode_ci)
            JOIN `dms_laravel`.`jenis_kendaraan` nj ON nj.bidang_id = nb.id AND nj.nama = TRIM(s.jenis_kendaraan) COLLATE utf8mb4_unicode_ci
            JOIN `dms_laravel`.`kendaraan` nk ON nk.jenis_kendaraan_id = nj.id AND nk.nomor_kendaraan = TRIM(s.nomor_kendaraan) COLLATE utf8mb4_unicode_ci
        ) c
        WHERE c.sb >= 0          -- total Jalan+Rusak+Perbaikan+PM lebih dari 24 jam: dilewati
    ) g
) h
ORDER BY h.id                    -- bila ada baris dobel di data lama, yang terakhir dipakai
ON DUPLICATE KEY UPDATE
    `bidang_id` = VALUES(`bidang_id`), `jenis_kendaraan_id` = VALUES(`jenis_kendaraan_id`),
    `jalan` = VALUES(`jalan`), `standby` = VALUES(`standby`), `rusak` = VALUES(`rusak`), `perbaikan` = VALUES(`perbaikan`), `pm` = VALUES(`pm`),
    `total_jam_kerja` = VALUES(`total_jam_kerja`), `availability_rate` = VALUES(`availability_rate`),
    `utilitas_jalan` = VALUES(`utilitas_jalan`), `utilitas_standby` = VALUES(`utilitas_standby`), `utilitas_rusak` = VALUES(`utilitas_rusak`),
    `updated_at` = VALUES(`updated_at`);

-- 6. Periode aktif kendaraan yang masih kosong ------------------------------------------------
--    aktif_sejak = tanggal data pertama (atau tanggal dibuat); kendaraan non-aktif: nonaktif_sejak = sehari setelah data terakhir
UPDATE `dms_laravel`.`kendaraan` k
SET k.aktif_sejak = COALESCE((SELECT MIN(s.tanggal) FROM `dms_laravel`.`sheet_data` s WHERE s.kendaraan_id = k.id), DATE(COALESCE(k.created_at, NOW())))
WHERE k.aktif_sejak IS NULL;

UPDATE `dms_laravel`.`kendaraan` k
SET k.nonaktif_sejak = COALESCE((SELECT MAX(s.tanggal) FROM `dms_laravel`.`sheet_data` s WHERE s.kendaraan_id = k.id) + INTERVAL 1 DAY, CURDATE())
WHERE k.is_active = 0 AND k.nonaktif_sejak IS NULL;

COMMIT;

-- 7. Ringkasan hasil ----------------------------------------------------------------------------
SELECT 'Baris data harian di DMS lama' AS keterangan, COUNT(*) AS jumlah FROM `db_dashboard`.`sheet_data`
UNION ALL SELECT 'Baris data harian di DMS v2', COUNT(*) FROM `dms_laravel`.`sheet_data`
UNION ALL SELECT CONCAT('Tanggal data terakhir: ', MAX(tanggal)), NULL FROM `dms_laravel`.`sheet_data`
UNION ALL SELECT 'Kendaraan di DMS v2 (aktif)', COUNT(*) FROM `dms_laravel`.`kendaraan` WHERE nonaktif_sejak IS NULL
UNION ALL SELECT 'Akun di DMS v2', COUNT(*) FROM `dms_laravel`.`users`;

-- 8. Perlu dicek: kendaraan aktif di DMS v2 yang sudah tidak ada di master DMS lama (mungkin dijual / dipindah).
--    Tidak diubah otomatis; nonaktifkan lewat Admin > Kendaraan bila memang sudah tidak beroperasi.
SELECT nb.nama AS bidang, nj.nama AS jenis, nk.nomor_kendaraan AS nomor
FROM `dms_laravel`.`kendaraan` nk
JOIN `dms_laravel`.`jenis_kendaraan` nj ON nj.id = nk.jenis_kendaraan_id
JOIN `dms_laravel`.`bidang` nb ON nb.id = nj.bidang_id
WHERE nk.nonaktif_sejak IS NULL AND nk.keterangan IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM `db_dashboard`.`kendaraan` k
      JOIN `db_dashboard`.`jenis_kendaraan` j ON j.id_jenis = k.id_jenis
      JOIN `db_dashboard`.`bidang` b ON b.id_bidang = j.id_bidang
      WHERE TRIM(k.nomor_kendaraan) COLLATE utf8mb4_unicode_ci = nk.nomor_kendaraan
        AND TRIM(j.nama_jenis) COLLATE utf8mb4_unicode_ci = nj.nama
        AND (CASE TRIM(b.nama_bidang) WHEN 'ALBER WIL 1 PG' THEN 'ALBER WIL 1' WHEN 'ALBER WIL 2 PG' THEN 'ALBER WIL 2'
                 WHEN 'ALBER WIL 2 FI' THEN 'ALBER WIL 3' ELSE TRIM(b.nama_bidang) END COLLATE utf8mb4_unicode_ci) = nb.nama
  )
ORDER BY nb.urutan, nj.nama, nk.nomor_kendaraan;
