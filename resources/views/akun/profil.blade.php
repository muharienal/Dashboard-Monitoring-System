@extends('layouts.app')
@section('title', 'Ganti Password')
@section('content')
<div class="page-head"><div><h1>Ganti Password</h1><div class="sub">Nama pengguna: <b>{{ auth()->user()->username }}</b></div></div></div>
<div class="card-dms" style="max-width:460px"><div class="card-body">
    <form method="POST" action="{{ route('profil.password') }}" data-confirm="Gunakan password baru saat masuk berikutnya." data-confirm-title="Ganti password?" data-confirm-ok="Ganti password" data-confirm-icon="fa-key">@csrf @method('PUT')
        <div class="mb-3"><label class="form-label" for="pl">Password sekarang</label><input type="password" class="form-control" id="pl" name="password_lama" required autocomplete="current-password" placeholder="Masukkan password yang dipakai sekarang"></div>
        <div class="mb-3"><label class="form-label" for="pb">Password baru</label><input type="password" class="form-control" id="pb" name="password" required minlength="8" autocomplete="new-password" placeholder="Minimal 8 karakter"></div>
        <div class="mb-3"><label class="form-label" for="pk">Ulangi password baru</label><input type="password" class="form-control" id="pk" name="password_confirmation" required minlength="8" autocomplete="new-password" placeholder="Ketik ulang password baru"></div>
        <button class="btn btn-dms">Simpan</button>
    </form>
</div></div>

<div class="card-dms mt-3" style="max-width:720px">
    <div class="card-head"><h2>Aktivitas login akun Anda</h2><span class="hint">10 terakhir</span></div>
    <div class="card-body p-0">
        <table class="table table-dms table-sm mb-0">
            <thead><tr><th>Waktu</th><th>Hasil</th><th>Perangkat</th><th>IP</th></tr></thead>
            <tbody>
            @forelse ($login as $r)
                <tr><td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($r->created_at)->translatedFormat('j M Y H:i') }}</td>
                    <td>{{ \App\Support\RiwayatLogin::HASIL[$r->hasil] ?? $r->hasil }}</td><td>{{ $r->perangkat ?? '–' }}</td><td class="num">{{ $r->ip ?? '–' }}</td></tr>
            @empty
                <tr><td colspan="4" class="empty-state py-3">Belum ada catatan.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="pb-ket">Ada yang tidak Anda kenali (waktu, perangkat, atau percobaan gagal)? Segera ganti password di atas dan beri tahu admin.</div>
    </div>
</div>
@endsection
