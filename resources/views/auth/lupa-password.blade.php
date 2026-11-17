<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lupa password · DMS v2</title>
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
    <h1>Lupa password</h1>
    <p class="lupa-intro">Admin akan mengatur password baru dan memberi tahu Anda.</p>

    @include('partials.flash', ['judulError' => 'Belum terkirim'])

    <form action="{{ route('lupa-password.kirim') }}" method="POST" id="formLupa" novalidate
        data-confirm="Admin akan menerima permintaan reset password untuk akun ini." data-confirm-title="Kirim permintaan?" data-confirm-ok="Kirim" data-confirm-icon="fa-paper-plane">
        @csrf
        <div class="mb-3">
            <label for="username" class="form-label">Nama pengguna</label>
            <div class="input-icon">
                <i class="fas fa-user" aria-hidden="true"></i>
                <input type="text" id="username" name="username" value="{{ is_string(old('username')) ? old('username') : '' }}" class="form-control @error('username') is-invalid @enderror" placeholder="Masukkan nama pengguna" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false" enterkeyhint="next" aria-describedby="usernameHint" maxlength="255">
            </div>
            <div id="usernameHint" class="form-text">Contoh: admin (tanpa @gmail.com)</div>
        </div>
        <div class="mb-3">
            <label for="catatan" class="form-label">Catatan untuk admin <span class="text-secondary small">(opsional)</span></label>
            <input type="text" id="catatan" name="catatan" value="{{ is_string(old('catatan')) ? old('catatan') : '' }}" class="form-control" placeholder="Mis. nomor HP yang bisa dihubungi" maxlength="255" enterkeyhint="send">
        </div>
        <button type="submit" class="btn btn-dms btn-login">Kirim permintaan</button>
    </form>
    <a class="link-kembali" href="{{ route('login') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali ke halaman masuk</a>
    @include('partials.kaki', ['kakiKelas' => 'footer-text'])
</main>
<div id="toastArea" aria-live="polite"></div>
<script src="{{ asset('assets/js/alert.js') }}?v={{ filemtime(public_path('assets/js/alert.js')) }}"></script>
<script>
(function () {
    const form = document.getElementById('formLupa'), user = document.getElementById('username'), cat = document.getElementById('catatan');
    user.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' || e.isComposing) return;
        e.preventDefault();
        if (!user.value.trim()) { user.classList.add('is-invalid'); return; }
        cat.focus();
    });
    user.addEventListener('input', () => user.classList.remove('is-invalid'));
    form.addEventListener('submit', (e) => {
        if (user.value.trim()) return;
        e.preventDefault(); e.stopImmediatePropagation();
        user.classList.add('is-invalid');
        DMSAlert.toast('Isi nama pengguna terlebih dahulu.', 'warning', 3500, { title: 'Belum lengkap' });
        user.focus();
    }, true);
})();
</script>
</body>
</html>
