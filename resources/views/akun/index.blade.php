@extends('layouts.app')
@section('title', 'Akun')
@php
    $sortLink = fn ($col) => request()->fullUrlWithQuery(['sort' => $col, 'dir' => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc', 'page' => 1]);
    $icon = fn ($col) => $sort === $col ? ($dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort';
    $th = fn ($col, $label) => '<a class="text-decoration-none" href="'.e($sortLink($col)).'">'.$label.' <i class="fas '.$icon($col).' small opacity-75"></i></a>';
@endphp
@section('content')
<div class="page-head">
    <div><h1>Akun</h1></div>
    <button class="btn btn-sm btn-dms" data-bs-toggle="modal" data-bs-target="#modalUser" data-mode="add"><i class="fas fa-plus"></i> Tambah</button>
</div>

@if ($permintaan->isNotEmpty())
<section class="permintaan-reset" id="permintaanReset" aria-labelledby="prJudul">
    <div class="pr-head">
        <span class="pr-ikon" aria-hidden="true"><i class="fas fa-key"></i></span>
        <div><h2 id="prJudul">Permintaan reset password <span class="pr-jumlah">{{ $permintaan->count() }}</span></h2>
            <span>Dikirim dari tombol "Lupa password" di halaman login. Atur password baru, lalu beri tahu yang bersangkutan.</span></div>
    </div>
    @foreach ($permintaan as $p)
        <div class="pr-baris">
            <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($p->user->name, 0, 1)) }}</span>
            <div class="pr-siapa">
                <b>{{ $p->user->name }}</b>
                <span>{{ $p->user->username }} · {{ $p->user->role_label }}{{ $p->user->bidang ? ' · '.$p->user->bidang->nama_tampil : '' }}</span>
                @if ($p->catatan)<q>{{ $p->catatan }}</q>@endif
            </div>
            <span class="pr-waktu" title="{{ $p->updated_at->translatedFormat('j F Y H:i') }}">{{ $p->updated_at->diffForHumans() }}</span>
            <div class="pr-aksi">
                <button type="button" class="btn btn-sm btn-dms" data-bs-toggle="modal" data-bs-target="#modalReset"
                    data-action="{{ route('akun.reset.selesai', $p) }}" data-nama="{{ $p->user->name }}" data-username="{{ $p->user->username }}">Atur password baru</button>
                <form method="POST" action="{{ route('akun.reset.tolak', $p) }}" data-confirm="Permintaan dari {{ $p->user->name }} ditutup tanpa mengganti password." data-confirm-title="Tolak permintaan?" data-confirm-ok="Tolak" data-confirm-tone="danger" data-confirm-icon="fa-ban">@csrf
                    <button class="btn btn-sm btn-outline-secondary">Tolak</button></form>
            </div>
        </div>
    @endforeach
</section>

<div class="modal fade" id="modalReset" tabindex="-1" aria-hidden="true" aria-labelledby="mrTitle">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" id="formReset">
        @csrf
        <div class="modal-header"><h2 class="modal-title h5" id="mrTitle">Atur password baru</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
        <div class="modal-body">
            <p class="mb-3">Untuk <strong id="mrNama"></strong> <span class="text-secondary" id="mrUsername"></span></p>
            <div class="row g-2 mb-1">
                <div class="col"><label class="form-label" for="mrPass">Password baru</label><input type="password" class="form-control" id="mrPass" name="password" required minlength="8" autocomplete="new-password" placeholder="Minimal 8 karakter"></div>
                <div class="col"><label class="form-label" for="mrPass2">Ulangi password</label><input type="password" class="form-control" id="mrPass2" name="password_confirmation" required minlength="8" autocomplete="new-password" placeholder="Ketik ulang password baru"></div>
            </div>
            <div class="form-text">Pengguna akan keluar dari semua perangkat.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-sm btn-dms">Simpan password</button></div>
    </form></div>
</div>
@endif

<form class="filter-bar filter-block" method="GET" id="fAkun">
    <input type="hidden" name="sort" value="{{ $sort }}"><input type="hidden" name="dir" value="{{ $dir }}">
    <div class="flex-grow-1" style="min-width:200px"><label class="form-label" for="q">Cari</label><input type="search" class="form-control form-control-sm" id="q" name="q" value="{{ $f['q'] }}" placeholder="Nama atau nama pengguna"></div>
    <div><label class="form-label" for="fr">Role</label>
        <select class="form-select form-select-sm auto" id="fr" name="role"><option value="">Semua</option>
            @foreach (\App\Models\User::ROLES as $k => $v)<option value="{{ $k }}" @selected($f['role'] === $k)>{{ $v }}</option>@endforeach
        </select></div>
    <div><label class="form-label" for="fbd">Bidang</label>
        <select class="form-select form-select-sm auto" id="fbd" name="bidang"><option value="">Semua</option>
            @foreach ($bidangList as $b)<option value="{{ $b->kode }}" @selected($f['bidang'] === $b->kode)>{{ $b->nama_tampil }}</option>@endforeach
        </select></div>
    <div><label class="form-label" for="fst">Status</label>
        <select class="form-select form-select-sm auto" id="fst" name="status"><option value="">Semua</option>
            @foreach (['online' => 'Online', 'offline' => 'Offline', 'aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'] as $k => $v)<option value="{{ $k }}" @selected($f['status'] === $k)>{{ $v }}</option>@endforeach
        </select></div>
    <div class="d-flex gap-2"><button class="btn btn-sm btn-dms"><i class="fas fa-search"></i> Cari</button>
        @if (collect($f)->filter()->isNotEmpty())<a href="{{ route('akun.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>@endif</div>
</form>

<div class="table-wrap">
<table class="table table-dms table-hover mb-0 align-middle">
    <thead><tr>
        <th class="text-end" style="width:56px">No</th>
        <th>{!! $th('name', 'Nama') !!}</th>
        <th>{!! $th('username', 'Nama pengguna') !!}</th>
        <th>{!! $th('role', 'Role') !!}</th>
        <th>Status</th>
        <th>{!! $th('last_login_at', 'Login terakhir') !!}</th>
        <th class="text-end">Aksi</th>
    </tr></thead>
    <tbody>
    @forelse ($users as $i => $u)
        @php($on = $online[$u->id] ?? null)
        <tr>
            <td class="text-end num text-secondary">{{ $users->firstItem() + $i }}</td>
            <td class="fw-semibold">{{ $u->name }} @if ($u->id === auth()->id())<span class="badge text-bg-light border fw-normal">Anda</span>@endif</td>
            <td><code class="uname">{{ $u->username }}</code></td>
            <td>{{ $u->role_label }}@if ($u->bidang)<div class="small text-secondary">{{ $u->bidang->nama_tampil }}</div>@endif</td>
            <td>
                @if (! $u->is_active)<span class="badge text-bg-dark">Nonaktif</span>
                @elseif ($on)<span class="badge text-bg-success">Online</span>
                @else<span class="badge text-bg-light border text-secondary">Offline</span>@endif
            </td>
            <td class="small">{{ $u->last_login_at ? $u->last_login_at->format('d/m/Y H:i') : '–' }}</td>
            <td class="text-end text-nowrap">
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalUser" data-mode="edit"
                    data-id="{{ $u->id }}" data-name="{{ $u->name }}" data-username="{{ $u->username }}" data-role="{{ $u->role }}"
                    data-bidang="{{ $u->bidang_id }}" data-active="{{ $u->is_active ? 1 : 0 }}" title="Ubah" aria-label="Ubah {{ $u->name }}"><i class="fas fa-pen"></i></button>
                @if ($on && $u->id !== auth()->id())
                    <form class="d-inline" method="POST" action="{{ route('akun.logout', $u) }}" data-confirm="{{ $u->name }} akan keluar dari semua perangkat dan perlu masuk lagi." data-confirm-title="Paksa keluar?" data-confirm-ok="Keluarkan" data-confirm-tone="warning" data-confirm-icon="fa-right-from-bracket">@csrf
                        <button class="btn btn-sm btn-outline-secondary" title="Paksa keluar"><i class="fas fa-right-from-bracket"></i></button></form>
                @endif
                @if ($u->id !== auth()->id())
                    <form class="d-inline" method="POST" action="{{ route('akun.destroy', $u) }}" data-confirm="Akun {{ $u->name }} ({{ $u->username }}) dihapus permanen. Data yang pernah diinput tetap ada." data-confirm-title="Hapus akun?" data-confirm-ok="Hapus" data-confirm-tone="danger">@csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger" title="Hapus" aria-label="Hapus {{ $u->name }}"><i class="fas fa-trash"></i></button></form>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="empty-state">Tidak ada akun yang cocok.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<div class="mt-3">{{ $users->links('pagination::bootstrap-5') }}</div>

<div class="modal fade" id="modalUser" tabindex="-1" aria-hidden="true" aria-labelledby="muTitle">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" id="formUser" action="{{ route('akun.store') }}">
        @csrf <input type="hidden" name="_method" value="POST" id="muMethod">
        <div class="modal-header"><h2 class="modal-title h5" id="muTitle">Tambah Akun</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label" for="muName">Nama</label><input class="form-control" id="muName" name="name" required maxlength="100" placeholder="Mis. Budi Santoso" autocomplete="off" value="{{ is_string(old('name')) ? old('name') : '' }}"></div>
            <div class="mb-3"><label class="form-label" for="muUsername">Nama pengguna</label><input type="text" class="form-control" id="muUsername" name="username" required minlength="2" maxlength="50" pattern="[a-z0-9][a-z0-9._\-]{1,49}" value="{{ is_string(old('username')) ? old('username') : '' }}" placeholder="Mis. budi" autocomplete="off" autocapitalize="none" spellcheck="false" aria-describedby="muUsernameHint">
                <div class="form-text" id="muUsernameHint">Untuk login. Huruf kecil, angka, titik, garis bawah, atau strip, tanpa spasi.</div></div>
            <div class="row g-2 mb-1">
                <div class="col"><label class="form-label" for="muRole">Role</label>
                    <select class="form-select" id="muRole" name="role" required>@foreach (\App\Models\User::ROLES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div class="col" id="muBidangWrap"><label class="form-label" for="muBidang">Bidang</label>
                    <select class="form-select" id="muBidang" name="bidang_id"><option value="">Semua</option>@foreach ($bidangList as $b)<option value="{{ $b->id }}">{{ $b->nama_tampil }}</option>@endforeach</select></div>
            </div>
            <div class="form-text mb-3" id="muRoleHint"></div>
            <div class="row g-2 mb-3">
                <div class="col"><label class="form-label" for="muPass">Password</label><input type="password" class="form-control" id="muPass" name="password" minlength="8" autocomplete="new-password" placeholder="Minimal 8 karakter"></div>
                <div class="col"><label class="form-label" for="muPass2">Ulangi password</label><input type="password" class="form-control" id="muPass2" name="password_confirmation" minlength="8" autocomplete="new-password" placeholder="Ketik ulang password"></div>
            </div>
            <div class="form-check"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" id="muActive" name="is_active" value="1" checked><label class="form-check-label" for="muActive">Aktif</label></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-dms">Simpan</button></div>
    </form></div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const base = @json(url('akun'));
    const HINT = {
        admin: 'Akses penuh.',
        bidang: 'Input Jalan & Rusak. Pilih bidang untuk membatasi.',
        pemeliharaan: 'Input Perbaikan & PM.',
        manajemen: 'Hanya melihat dashboard.',
    };
    const role = document.getElementById('muRole'), wrap = document.getElementById('muBidangWrap');
    const syncRole = () => {
        wrap.style.visibility = role.value === 'bidang' ? 'visible' : 'hidden';
        document.getElementById('muRoleHint').textContent = HINT[role.value] || '';
    };
    role.addEventListener('change', syncRole);
    // Nama pengguna: otomatis huruf kecil dan tanpa spasi saat diketik
    const uname = document.getElementById('muUsername');
    uname.addEventListener('input', () => {
        const bersih = uname.value.toLowerCase().replace(/\s+/g, '');
        if (bersih !== uname.value) { const pos = uname.selectionStart - (uname.value.length - bersih.length); uname.value = bersih; try { uname.setSelectionRange(pos, pos); } catch (_) {} }
    });
    document.getElementById('modalUser').addEventListener('show.bs.modal', (e) => {
        const b = e.relatedTarget, edit = b?.dataset.mode === 'edit';
        if (!b) return;
        const f = document.getElementById('formUser');
        document.getElementById('muTitle').textContent = edit ? 'Ubah Akun' : 'Tambah Akun';
        f.action = edit ? `${base}/${b.dataset.id}` : base;
        document.getElementById('muMethod').value = edit ? 'PUT' : 'POST';
        document.getElementById('muName').value = edit ? b.dataset.name : '';
        document.getElementById('muUsername').value = edit ? b.dataset.username : '';
        role.value = edit ? b.dataset.role : 'bidang';
        document.getElementById('muBidang').value = edit ? (b.dataset.bidang || '') : '';
        document.getElementById('muActive').checked = edit ? b.dataset.active === '1' : true;
        document.getElementById('muPass').required = !edit;
        document.getElementById('muPass').value = document.getElementById('muPass2').value = '';
        document.getElementById('muPass').placeholder = edit ? 'Kosongkan bila tidak diganti' : 'Minimal 8 karakter';
        document.getElementById('muPass2').placeholder = edit ? 'Isi hanya bila mengganti password' : 'Ketik ulang password';
        syncRole();
    });
    syncRole();
    const mr = document.getElementById('modalReset');
    if (mr) {
        mr.addEventListener('show.bs.modal', (e) => {
            const b = e.relatedTarget;
            document.getElementById('formReset').action = b.dataset.action;
            document.getElementById('mrNama').textContent = b.dataset.nama;
            document.getElementById('mrUsername').textContent = '(' + b.dataset.username + ')';
            document.getElementById('mrPass').value = document.getElementById('mrPass2').value = '';
        });
        mr.addEventListener('shown.bs.modal', () => document.getElementById('mrPass').focus());
        document.getElementById('formReset').dmsConfirm = () => {
            const p1 = document.getElementById('mrPass').value, p2 = document.getElementById('mrPass2').value;
            if (p1 !== p2) { DMS.toast('Kedua password belum sama.', 'warning', 3500, { title: 'Periksa lagi' }); document.getElementById('mrPass2').focus(); return false; }
            return { title: 'Ganti password?', ok: 'Ganti password', icon: 'fa-key',
                text: `Password ${document.getElementById('mrNama').textContent} diganti dan ia keluar dari semua perangkat.` };
        };
    }
    document.getElementById('formUser').dmsConfirm = (f) => {
        const ubah = document.getElementById('muMethod').value === 'PUT';
        const nama = document.getElementById('muName').value.trim();
        const nonaktif = !document.getElementById('muActive').checked;
        return ubah
            ? { title: 'Simpan perubahan akun?', ok: 'Simpan', icon: 'fa-user-pen', tone: nonaktif ? 'warning' : 'primary',
                text: `Akun ${nama}${nonaktif ? ' akan dinonaktifkan dan tidak bisa masuk' : ' akan diperbarui'}.` }
            : { title: 'Tambah akun?', ok: 'Tambah', icon: 'fa-user-plus',
                text: `${nama} (${role.options[role.selectedIndex].text}) akan bisa masuk ke DMS v2.` };
    };
    document.querySelectorAll('#fAkun select.auto').forEach((el) => el.addEventListener('change', () => el.form.submit()));
    @if ($errors->any() && old('username') !== null)
        new bootstrap.Modal('#modalUser').show();
    @endif
})();
</script>
@endpush
