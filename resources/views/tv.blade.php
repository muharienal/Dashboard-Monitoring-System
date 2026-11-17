<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mode TV · DMS v2</title>
    @include('partials.favicon')
    <link rel="stylesheet" href="{{ asset('assets/vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/tv.css') }}?v={{ filemtime(public_path('assets/css/tv.css')) }}">
</head>
<body class="tv">
<div class="tv-progres" aria-hidden="true"><span id="tvProgres"></span></div>
<header class="tv-head">
    <div class="tv-merek">@include('partials.logo-dms')<div><strong>DMS v2 · Dashboard Management System</strong><span>{{ config('dms.perusahaan') }}</span></div></div>
    <div class="tv-judul"><h1 id="tvJudul">Semua bidang</h1><span id="tvPeriode">–</span></div>
    <div class="tv-jam"><strong id="tvJam">--:--</strong><span id="tvTanggal">–</span></div>
</header>

<main class="tv-isi" aria-live="polite">
    <section class="tv-kpi">
        <div class="tv-k"><span class="l">Availability</span><b id="tkAr">–</b><span class="d" id="tdAr"></span></div>
        <div class="tv-k"><span class="l">Utilisasi</span><b id="tkUtil">–</b><span class="d" id="tdUtil"></span></div>
        <div class="tv-k"><span class="l" id="tlUnit">Jumlah unit</span><b id="tkUnit">–</b><span class="d" id="tdUnit"></span></div>
        <div class="tv-k"><span class="l">Jam perbaikan</span><b id="tkPerb">–</b><span class="d" id="tdPerb"></span></div>
    </section>
    <section class="tv-grafik">
        <div class="tv-panel tv-tren"><h2 id="tvTrenJudul">Unit per hari</h2><div class="tv-chart"><canvas id="tvTren" role="img" aria-label="Tren unit per hari"></canvas></div><div class="tv-catatan" id="tvTrenCat"></div></div>
        <div class="tv-panel tv-samping"><h2 id="tvSampingJudul">Per bidang</h2><div id="tvSamping"></div></div>
    </section>
</main>

<footer class="tv-kaki">
    <div class="tv-titik" id="tvTitik" role="tablist" aria-label="Halaman"></div>
    <div class="tv-status"><span id="tvKelengkapan"></span><span id="tvDiperbarui"></span><span class="tv-offline" id="tvOffline" hidden><i class="fas fa-wifi"></i> Koneksi terputus, mencoba lagi…</span></div>
    <div class="tv-kontrol">
        <button type="button" id="tvPrev" aria-label="Sebelumnya"><i class="fas fa-chevron-left"></i></button>
        <button type="button" id="tvJeda" aria-label="Jeda"><i class="fas fa-pause"></i></button>
        <button type="button" id="tvNext" aria-label="Berikutnya"><i class="fas fa-chevron-right"></i></button>
        <button type="button" id="tvIngat" title="Tetap masuk di layar ini, walau sesi habis"><i class="fas fa-lock-open"></i> Tetap masuk</button>
        <button type="button" id="tvPenuh"><i class="fas fa-expand"></i> Layar penuh</button>
        <a href="{{ route('beranda') }}" id="tvKeluar"><i class="fas fa-xmark"></i> Tutup</a>
    </div>
    @include('partials.kaki', ['kakiKelas' => 'tv-hakcipta'])
</footer>
<div id="toastArea" aria-live="polite"></div>

<script>
    window.DMS_CONFIG = {
        refresh: {{ (int) config('dms.refresh_detik') }},
        versionUrl: @json(route('version')),
        loginUrl: @json(route('login')),
        profilUrl: @json(url('kendaraan')),
        cariUrl: @json(route('kendaraan.cari')),
        warna: @json(config('dms.warna')),
    };
    window.TV = {
        bidang: @json($bidang),
        detik: {{ $detik }},
        url: { beranda: @json(route('beranda.data')), summary: @json(url('api/dashboard/summary')), ingat: @json(route('tv.ingat')) },
    };
</script>
<script src="{{ asset('assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/js/alert.js') }}?v={{ filemtime(public_path('assets/js/alert.js')) }}"></script>
<script src="{{ asset('assets/vendor/chartjs/chart.umd.min.js') }}"></script>
<script src="{{ asset('assets/vendor/chartjs/chartjs-plugin-datalabels.min.js') }}"></script>
<script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}"></script>
<script src="{{ asset('assets/js/tv.js') }}?v={{ filemtime(public_path('assets/js/tv.js')) }}"></script>
</body>
</html>
