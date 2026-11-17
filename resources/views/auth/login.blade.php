<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · DMS v2</title>
    @include('partials.favicon')
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
</head>
<body class="login-body">
<main class="login-card">
    <div class="login-brand">
        @include('partials.logo-dms')
        <span class="logo-sep" aria-hidden="true"></span>
        <img src="{{ asset('assets/img/LOGO-CORP.png') }}" alt="PT Petrokopindo Cipta Selaras" class="logo">
    </div>
    <h1>DMS v2</h1>

    <div class="v2-note" role="note">
        <span class="tag">Baru</span>
        <span>Tampilan baru dan fitur baru. Masuk dengan nama pengguna dan password yang sama seperti sebelumnya.</span>
    </div>

    @include('partials.flash', ['judulError' => 'Gagal masuk'])

    <form action="{{ route('login.attempt') }}" method="POST" id="formLogin" novalidate>
        @csrf
        <div class="mb-3">
            <label for="username" class="form-label">Nama pengguna</label>
            <div class="input-icon">
                <i class="fas fa-user" aria-hidden="true"></i>
                <input type="text" id="username" name="username" value="{{ is_string(old('username')) ? old('username') : '' }}" class="form-control @error('username') is-invalid @enderror" placeholder="Masukkan nama pengguna" enterkeyhint="next" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false" aria-describedby="usernameHint">
            </div>
            <div id="usernameHint" class="form-text">Contoh: admin (tanpa @gmail.com)</div>
        </div>
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-baseline"><label for="password" class="form-label">Password</label><a class="link-lupa" href="{{ route('lupa-password') }}">Lupa password?</a></div>
            <div class="input-icon">
                <i class="fas fa-lock" aria-hidden="true"></i>
                <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan password" enterkeyhint="go" required autocomplete="current-password">
            </div>
        </div>
        <button type="submit" class="btn btn-dms btn-login">Masuk</button>
    </form>
    @include('partials.kaki', ['kakiKelas' => 'footer-text'])
</main>
<div id="toastArea" aria-live="polite"></div>
<script src="{{ asset('assets/js/alert.js') }}?v={{ filemtime(public_path('assets/js/alert.js')) }}"></script>
<script src="{{ asset('assets/js/password.js') }}?v={{ filemtime(public_path('assets/js/password.js')) }}"></script>
<script>
(function () {
    const form = document.getElementById('formLogin');
    const user = document.getElementById('username'), pass = document.getElementById('password');
    // Enter di nama pengguna: pindah ke password (bukan langsung kirim).
    user.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' || e.isComposing) return;
        e.preventDefault();
        if (!user.value.trim()) { user.classList.add('is-invalid'); return; }
        pass.focus();
        pass.select();
    });
    user.addEventListener('input', () => user.classList.remove('is-invalid'));
    pass.addEventListener('input', () => pass.classList.remove('is-invalid'));
    // Kolom kosong langsung diberi tahu tanpa menunggu server.
    form.addEventListener('submit', (e) => {
        const kosong = [[user, 'nama pengguna'], [pass, 'password']].filter(([el]) => !el.value.trim());
        if (!kosong.length) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        kosong.forEach(([el]) => el.classList.add('is-invalid'));
        DMSAlert.toast('Isi ' + kosong.map(([, n]) => n).join(' dan ') + ' terlebih dahulu.', 'warning', 3500, { title: 'Belum lengkap' });
        kosong[0][0].focus();
    }, true);
    // Setelah gagal login, kursor langsung di password.
    if (user.value && user.classList.contains('is-invalid')) { user.classList.remove('is-invalid'); pass.classList.add('is-invalid'); pass.focus(); }
})();
</script>
</body>
</html>
