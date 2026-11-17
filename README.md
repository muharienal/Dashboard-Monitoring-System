# DMS v2 – Dashboard Management System (Laravel)

## Instalasi

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
php artisan db:seed --force     # admin awal: admin@dms.local / Admin#12345 (segera ganti)

# 6. Optimasi untuk produksi
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
