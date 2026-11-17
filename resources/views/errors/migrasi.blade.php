<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Perlu pembaruan · DMS v2</title>
    @include('partials.favicon')
<link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}"><link rel="stylesheet" href="{{ asset('assets/css/app.css') }}"></head>
<body class="error-body"><main class="error-card" style="max-width:520px">
    <img src="{{ asset('assets/img/LOGO-CORP.png') }}" alt="PT Petrokopindo Cipta Selaras">
    <h1 class="h4 fw-bold mb-2">Database perlu diperbarui</h1>
    <p class="text-secondary">Aplikasi sudah versi terbaru, tetapi strukturnya di database belum. Jalankan perintah berikut di folder aplikasi, lalu muat ulang halaman ini:</p>
    <pre class="text-start bg-light border rounded p-3 small mb-3">php artisan migrate
php artisan optimize:clear</pre>
    <p class="small text-secondary mb-0">Pembaruan yang belum dijalankan: {{ count($pending) }}</p>
@include('partials.kaki', ['kakiKelas' => 'kaki-error'])
</main></body></html>
