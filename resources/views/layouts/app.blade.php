<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · DMS v2</title>
    @include('partials.favicon')
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
    @stack('head')
</head>
<body>
<a class="visually-hidden-focusable position-absolute p-2 bg-white" href="#main">Lewati ke konten</a>
@include('partials.sidebar')
<div class="sidebar-backdrop"></div>

<main class="content" id="main">
    <div class="brand-stripe" aria-hidden="true"></div>
    <div class="topbar">
        <button class="btn btn-sm btn-outline-light" data-toggle-sidebar aria-label="Buka menu"><i class="fas fa-bars"></i></button>
        @include('partials.cari-unit', ['id' => 'cariTopbar', 'kelas' => 'di-topbar'])
    </div>

    @include('partials.flash')

    @yield('content')

    @include('partials.kaki', ['kakiKelas' => 'kaki-dms'])
</main>
<div id="toastArea" aria-live="polite"></div>
@include('partials.panduan')
@include('partials.modal-unduh')

<script>
    window.DMS_CONFIG = {
        refresh: {{ (int) config('dms.refresh_detik') }},
        versionUrl: @json(route('version')),
        loginUrl: @json(route('login')),
        profilUrl: @json(url('kendaraan')),
        cariUrl: @json(route('kendaraan.cari')),
        idleMenit: {{ (int) config('dms.idle_menit') }},
        aktifUrl: @json(route('aktif')),
        logoutUrl: @json(route('logout')),
        warna: @json(config('dms.warna')),
    };
</script>
<script src="{{ asset('assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/js/alert.js') }}?v={{ filemtime(public_path('assets/js/alert.js')) }}"></script>
<script src="{{ asset('assets/js/password.js') }}?v={{ filemtime(public_path('assets/js/password.js')) }}"></script>
@stack('vendor')
<script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}"></script>
@stack('scripts')
</body>
</html>
