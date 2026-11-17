<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>500 · DMS v2</title>
    @include('partials.favicon')
<link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}"><link rel="stylesheet" href="{{ asset('assets/css/app.css') }}"></head>
<body class="error-body"><main class="error-card"><img src="{{ asset('assets/img/LOGO-CORP.png') }}" alt="PT Petrokopindo Cipta Selaras"><h1 class="h2 fw-bold mb-2">500</h1>
<p class="text-secondary mb-4">Terjadi kesalahan di server. Coba lagi atau hubungi IT.</p><a href="{{ url('/') }}" class="btn btn-dms">Kembali ke Beranda</a>@include('partials.kaki', ['kakiKelas' => 'kaki-error'])</main></body></html>
