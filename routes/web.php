<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LupaPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InputController;
use App\Http\Controllers\KendaraanController;
use App\Http\Controllers\LaporanBulananController;
use App\Http\Controllers\PerbandinganController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\ProfilKendaraanController;
use App\Http\Controllers\RiwayatController;
use App\Http\Controllers\TvController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
    Route::get('/lupa-password', [LupaPasswordController::class, 'show'])->name('lupa-password');
    Route::post('/lupa-password', [LupaPasswordController::class, 'kirim'])->name('lupa-password.kirim');
});

Route::middleware(['auth', 'active'])->group(function () {
    // Halaman lama yang digabung ke Laporan
    Route::get('/dashboard/detail-report', fn () => redirect()->route('dashboard.laporan', ['tab' => 'detail'] + request()->query()));
    Route::get('/dashboard/general-report', fn () => redirect()->route('dashboard.laporan', ['tab' => 'rekap'] + request()->query()));

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    // Sinyal "masih aktif" dari browser (mouse/keyboard/sentuh) untuk fitur keluar otomatis.
    Route::post('/api/aktif', fn () => response()->noContent())->name('aktif');

    Route::get('/', [HomeController::class, 'index'])->name('beranda');
    Route::get('/api/beranda', [HomeController::class, 'data'])->name('beranda.data');
    Route::get('/api/version', [HomeController::class, 'version'])->name('version');

    // Input data harian (pengganti sheetAnglu / sheetAngdal / sheetAlberWil*.php)
    Route::middleware('role:admin,bidang,pemeliharaan')->prefix('input')->name('input.')->group(function () {
        Route::get('/{bidang}', [InputController::class, 'show'])->name('show');
        Route::get('/{bidang}/data', [InputController::class, 'data'])->name('data');
        Route::get('/{bidang}/ekspor', [InputController::class, 'ekspor'])->name('ekspor');
        Route::post('/{bidang}/simpan', [InputController::class, 'simpan'])->name('simpan');
    });

    // Dashboard (pengganti Tableau)
    Route::prefix('dashboard')->name('dashboard.')->controller(DashboardController::class)->group(function () {
        Route::get('/utilisasi', 'utilisasi')->name('utilisasi');
        Route::get('/availability', 'availability')->name('availability');
        Route::get('/maintenance', 'maintenance')->name('maintenance');
        Route::get('/laporan', 'laporan')->name('laporan');
        Route::get('/summary/{bidang}', 'summary')->name('summary');
    });
    Route::get('/dashboard/perbandingan', [PerbandinganController::class, 'index'])->name('dashboard.perbandingan');
    // Mode TV: khusus admin (dipasang di PC ruang kontrol)
    Route::middleware('role:admin')->group(function () {
        Route::get('/tv', [TvController::class, 'show'])->name('tv');
        Route::post('/tv/ingat', [TvController::class, 'ingat'])->name('tv.ingat');
    });
    Route::prefix('api/dashboard')->name('api.dashboard.')->controller(DashboardController::class)->group(function () {
        Route::get('/utilisasi', 'utilisasiData')->name('utilisasi');
        Route::get('/availability', 'availabilityData')->name('availability');
        Route::get('/maintenance', 'maintenanceData')->name('maintenance');
        Route::get('/detail-report', 'reportData')->name('report');
        Route::get('/general-report', 'generalData')->name('general');
        Route::get('/summary/{bidang}', 'summaryData')->name('summary');
    });

    // Profil & pencarian kendaraan
    Route::get('/kendaraan/{kendaraan}', [ProfilKendaraanController::class, 'show'])->whereNumber('kendaraan')->name('kendaraan.profil');
    Route::get('/api/cari-kendaraan', [ProfilKendaraanController::class, 'cari'])->name('kendaraan.cari');

    // Laporan bulanan siap cetak / PDF
    Route::get('/laporan/bulanan', [LaporanBulananController::class, 'show'])->name('laporan.bulanan');

    // Riwayat: data input untuk semua role (petugas bidang hanya bidangnya); tab Data master & Login khusus admin
    Route::get('/riwayat', [RiwayatController::class, 'index'])->name('riwayat.index');

    Route::get('/profil', [ProfilController::class, 'edit'])->name('profil');
    Route::put('/profil/password', [ProfilController::class, 'updatePassword'])->name('profil.password');

    // Admin
    Route::middleware('role:admin')->group(function () {
        Route::prefix('master')->name('master.')->controller(KendaraanController::class)->group(function () {
            Route::get('/kendaraan', 'index')->name('kendaraan');
            Route::post('/kendaraan', 'store')->name('kendaraan.store');
            Route::put('/kendaraan/{kendaraan}', 'update')->name('kendaraan.update');
            Route::patch('/kendaraan/{kendaraan}/status', 'toggle')->name('kendaraan.toggle');
            Route::delete('/kendaraan/{kendaraan}', 'destroy')->name('kendaraan.destroy');
            Route::post('/jenis', 'storeJenis')->name('jenis.store');
            Route::put('/jenis/{jenis}', 'updateJenis')->name('jenis.update');
            Route::delete('/jenis/{jenis}', 'destroyJenis')->name('jenis.destroy');
            Route::put('/bidang/{bidang:id}', 'updateBidang')->name('bidang.update');
        });
        Route::prefix('akun')->name('akun.')->controller(UserController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('/{user}', 'update')->name('update');
            Route::delete('/{user}', 'destroy')->name('destroy');
            Route::post('/{user}/logout', 'forceLogout')->name('logout');
            Route::post('/permintaan/{permintaan}/selesai', 'resetSelesai')->name('reset.selesai');
            Route::post('/permintaan/{permintaan}/tolak', 'resetTolak')->name('reset.tolak');
        });
    });
});

// Kompatibilitas URL lama (bookmark pengguna tetap jalan)
$lama = [
    'sheetAnglu' => '/input/angkutan-luar', 'sheetAngdal' => '/input/angkutan-dalam',
    'sheetAlberWil1pg' => '/input/alber-wil-1', 'sheetAlberWil2pg' => '/input/alber-wil-2', 'sheetAlberWil2fi' => '/input/alber-wil-3',
    'dashboardAnglu' => '/dashboard/summary/angkutan-luar', 'dashboardAngdal' => '/dashboard/summary/angkutan-dalam',
    'dashboardAlberWil1' => '/dashboard/summary/alber-wil-1', 'dashboardAlberWil2' => '/dashboard/summary/alber-wil-2',
    'dashboardAlberWil3' => '/dashboard/summary/alber-wil-3', 'angkutanLuar' => '/dashboard/summary/angkutan-luar',
    'utilisasi' => '/dashboard/utilisasi', 'availability' => '/dashboard/availability', 'maintenance' => '/dashboard/maintenance',
    'reports' => '/dashboard/laporan?tab=detail', 'GeneralReports' => '/dashboard/laporan?tab=rekap',
    'masterKendaraan' => '/master/kendaraan', 'account' => '/akun',
];
foreach ($lama as $dari => $ke) {
    Route::permanentRedirect($dari, $ke);
    Route::permanentRedirect($dari.'.php', $ke);
}
Route::permanentRedirect('login.php', '/login');
