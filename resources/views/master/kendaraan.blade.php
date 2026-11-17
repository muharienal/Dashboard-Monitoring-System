@extends('layouts.app')
@section('title', 'Kendaraan')
@php
    $jenisOptions = $bidangList->mapWithKeys(fn ($b) => [$b->id => $b->jenisKendaraan->map(fn ($j) => ['id' => $j->id, 'nama' => $j->nama])]);
    $jenisPerBidang = $bidangList->mapWithKeys(fn ($b) => [$b->kode => $b->jenisKendaraan->pluck('nama')]);
    $sortLink = fn ($col) => request()->fullUrlWithQuery(['sort' => $col, 'dir' => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc', 'page' => 1]);
    $th = fn ($col, $label) => '<a href="'.e($sortLink($col)).'">'.$label.' <i class="fas '.($sort === $col ? ($dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort').' small opacity-50"></i></a>';
    $tgl = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->translatedFormat('j M Y') : '–';
    $adaFilter = collect($f)->filter()->isNotEmpty();
@endphp
@section('content')
<div class="page-head">
    <div><h1>Kendaraan</h1><div class="sub">Kendaraan aktif muncul di form input.</div></div>
    <button class="btn btn-sm btn-dms" data-bs-toggle="modal" data-bs-target="#modalKendaraan" data-mode="add"><i class="fas fa-plus"></i> Tambah</button>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabKendaraan" type="button" role="tab">Kendaraan</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabJenis" type="button" role="tab">Jenis</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabBidang" type="button" role="tab">Bidang</button></li>
</ul>

<div class="tab-content">
<div class="tab-pane fade show active" id="tabKendaraan" role="tabpanel">
    <form class="filter-bar filter-block" method="GET" id="fKendaraan">
        <input type="hidden" name="sort" value="{{ $sort }}"><input type="hidden" name="dir" value="{{ $dir }}">
        <div class="flex-grow-1" style="min-width:200px"><label class="form-label" for="q">Cari</label>
            <input type="search" class="form-control form-control-sm" id="q" name="q" value="{{ $f['q'] }}" placeholder="Nomor, jenis, atau keterangan"></div>
        <div><label class="form-label" for="fb">Bidang</label>
            <select class="form-select form-select-sm auto" id="fb" name="bidang"><option value="">Semua</option>
                @foreach ($bidangList as $b)<option value="{{ $b->kode }}" @selected($f['bidang'] === $b->kode)>{{ $b->nama_tampil }}</option>@endforeach
            </select></div>
        <div><label class="form-label" for="fj">Jenis</label>
            <select class="form-select form-select-sm auto" id="fj" name="jenis"><option value="">Semua</option>
                @foreach ($jenisList as $j)<option value="{{ $j }}" @selected($f['jenis'] === $j)>{{ $j }}</option>@endforeach
            </select></div>
        <div><label class="form-label" for="fs">Status</label>
            <select class="form-select form-select-sm auto" id="fs" name="status"><option value="">Semua</option>
                <option value="aktif" @selected($f['status'] === 'aktif')>Aktif</option><option value="nonaktif" @selected($f['status'] === 'nonaktif')>Nonaktif</option>
            </select></div>
        <div><label class="form-label" for="fr">Riwayat</label>
            <select class="form-select form-select-sm auto" id="fr" name="riwayat"><option value="">Semua</option>
                <option value="ada" @selected($f['riwayat'] === 'ada')>Sudah ada data</option><option value="belum" @selected($f['riwayat'] === 'belum')>Belum ada data</option>
            </select></div>
        <div><label class="form-label" for="fp">Tampilkan</label>
            <select class="form-select form-select-sm auto" id="fp" name="per" style="min-width:84px">
                @foreach ([25, 50, 100] as $n)<option value="{{ $n }}" @selected($per === $n)>{{ $n }}</option>@endforeach
            </select></div>
        <div class="d-flex gap-2"><button class="btn btn-sm btn-dms"><i class="fas fa-search"></i> Cari</button>
            @if ($adaFilter)<a href="{{ route('master.kendaraan') }}" class="btn btn-sm btn-outline-secondary">Reset</a>@endif</div>
    </form>

    <div class="table-wrap">
        <table class="table table-dms table-hover mb-0">
            <thead><tr>
                <th class="text-end" style="width:56px">No</th>
                <th>{!! $th('nomor', 'Nomor') !!}</th><th>{!! $th('jenis', 'Jenis') !!}</th><th>{!! $th('bidang', 'Bidang') !!}</th>
                <th>Status</th><th>{!! $th('mulai', 'Mulai beroperasi') !!}</th>
                <th class="text-end">{!! $th('riwayat', 'Riwayat') !!}</th><th>{!! $th('terakhir', 'Data terakhir') !!}</th>
                <th class="text-end">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse ($kendaraan as $i => $k)
                <tr class="{{ $k->is_active ? '' : 'text-secondary' }}">
                    <td class="text-end num text-secondary">{{ $kendaraan->firstItem() + $i }}</td>
                    <td><a class="fw-bold nomor-link" href="{{ route('kendaraan.profil', $k->id) }}">{{ $k->nomor_kendaraan }}</a>@if ($k->keterangan)<div class="small text-secondary">{{ $k->keterangan }}</div>@endif</td>
                    <td>{{ $k->jenis }}</td><td>{{ $k->bidang }}</td>
                    <td>@if ($k->is_active)<span class="badge text-bg-success">Aktif</span>
                        @else<span class="badge text-bg-secondary">Nonaktif</span><div class="small text-secondary mt-1">sejak {{ $tgl($k->nonaktif_sejak) }}</div>@endif</td>
                    <td class="small">{{ $tgl($k->aktif_sejak) }}</td>
                    <td class="text-end num">{{ number_format($k->sheet_data_count, 0, ',', '.') }} hari</td>
                    <td class="small">{{ $tgl($k->sheet_data_max_tanggal) }}</td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalKendaraan" data-mode="edit"
                            data-id="{{ $k->id }}" data-bidang="{{ $k->bidang_id }}" data-jenis="{{ $k->jenis_kendaraan_id }}" data-nomor="{{ $k->nomor_kendaraan }}"
                            data-ket="{{ $k->keterangan }}" data-mulai="{{ $k->aktif_sejak?->toDateString() }}"
                            title="Ubah" aria-label="Ubah {{ $k->nomor_kendaraan }}"><i class="fas fa-pen"></i></button>
                        @if ($k->is_active)
                            <button class="btn btn-sm btn-outline-secondary" style="min-width:104px" data-bs-toggle="modal" data-bs-target="#modalNonaktif"
                                data-id="{{ $k->id }}" data-nomor="{{ $k->nomor_kendaraan }}" data-mulai="{{ $k->aktif_sejak?->toDateString() }}" data-terakhir="{{ $k->sheet_data_max_tanggal }}">Nonaktifkan</button>
                        @else
                            <form class="d-inline" method="POST" action="{{ route('master.kendaraan.toggle', $k) }}" data-confirm="{{ $k->nomor_kendaraan }} akan muncul lagi di form input." data-confirm-title="Aktifkan kembali?" data-confirm-ok="Aktifkan" data-confirm-icon="fa-power-off">@csrf @method('PATCH')
                                <input type="hidden" name="aksi" value="aktif"><button class="btn btn-sm btn-outline-secondary" style="min-width:104px">Aktifkan</button></form>
                        @endif
                        @if ($k->sheet_data_count === 0)
                            <form class="d-inline" method="POST" action="{{ route('master.kendaraan.destroy', $k) }}" data-confirm="{{ $k->nomor_kendaraan }} ({{ $k->jenis }}) dihapus permanen." data-confirm-title="Hapus kendaraan?" data-confirm-ok="Hapus" data-confirm-tone="danger">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Hapus" aria-label="Hapus {{ $k->nomor_kendaraan }}"><i class="fas fa-trash"></i></button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="empty-state">Tidak ada kendaraan yang cocok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $kendaraan->links('pagination::bootstrap-5') }}</div>
</div>

<div class="tab-pane fade" id="tabJenis" role="tabpanel">
    <div class="row g-3">
        @foreach ($bidangList as $b)
            <div class="col-md-6 col-xl-4"><div class="card-dms h-100">
                <div class="card-head"><h2>{{ $b->nama_tampil }}</h2></div>
                <div class="card-body">
                    <ul class="list-group list-group-flush mb-3">
                        @foreach ($b->jenisKendaraan as $j)
                            <li class="list-group-item d-flex align-items-center gap-2 px-0">
                                <form method="POST" action="{{ route('master.jenis.update', $j) }}" class="d-flex gap-1 flex-grow-1 f-jenis-ubah" data-lama="{{ $j->nama }}">@csrf @method('PUT')
                                    <input class="form-control form-control-sm" name="nama" value="{{ $j->nama }}" required placeholder="Nama jenis" aria-label="Nama jenis">
                                    <button class="btn btn-sm btn-outline-secondary" title="Simpan" aria-label="Simpan nama"><i class="fas fa-check"></i></button></form>
                                <span class="small text-secondary text-nowrap">{{ $j->kendaraan_count }} unit</span>
                                @if ($j->kendaraan_count === 0)
                                    <form method="POST" action="{{ route('master.jenis.destroy', $j) }}" data-confirm="Jenis {{ $j->nama }} di {{ $b->nama_tampil }} dihapus permanen." data-confirm-title="Hapus jenis?" data-confirm-ok="Hapus" data-confirm-tone="danger">@csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Hapus" aria-label="Hapus jenis"><i class="fas fa-trash"></i></button></form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <form method="POST" action="{{ route('master.jenis.store') }}" class="d-flex gap-1 f-jenis-tambah" data-bidang="{{ $b->nama_tampil }}">@csrf
                        <input type="hidden" name="bidang_id" value="{{ $b->id }}">
                        <input class="form-control form-control-sm" name="nama" placeholder="Jenis baru" required aria-label="Jenis baru">
                        <button class="btn btn-sm btn-dms" title="Tambah" aria-label="Tambah jenis"><i class="fas fa-plus"></i></button>
                    </form>
                </div>
            </div></div>
        @endforeach
    </div>
</div>

<div class="tab-pane fade" id="tabBidang" role="tabpanel">
    <div class="table-wrap"><table class="table table-dms mb-0">
            <thead><tr><th>Bidang</th><th>Nama di menu</th><th>Urutan</th><th>Semua kolom bebas diisi <i class="fas fa-circle-info info" title="Centang: petugas Bidang dan Pemeliharaan boleh mengisi semua kolom. Tidak dicentang: Bidang mengisi Jalan & Rusak, Pemeliharaan mengisi Perbaikan & PM."></i></th><th></th></tr></thead>
            <tbody>
            @foreach ($bidangList as $b)
                <tr>
                    <td>{{ $b->nama }}
                        <form id="fb-{{ $b->id }}" method="POST" action="{{ route('master.bidang.update', $b->id) }}" data-confirm="Nama di menu, urutan, dan hak isi kolom {{ $b->nama }} akan diperbarui." data-confirm-title="Simpan pengaturan bidang?" data-confirm-ok="Simpan" data-confirm-icon="fa-sliders">@csrf @method('PUT')<input type="hidden" name="input_semua_kolom" value="0"></form></td>
                    <td><input form="fb-{{ $b->id }}" class="form-control form-control-sm" name="nama_tampil" value="{{ $b->nama_tampil }}" required placeholder="{{ $b->nama }}" aria-label="Nama di menu"></td>
                    <td style="width:90px"><input form="fb-{{ $b->id }}" class="form-control form-control-sm" type="number" name="urutan" value="{{ $b->urutan }}" min="0" placeholder="0" aria-label="Urutan"></td>
                    <td><input form="fb-{{ $b->id }}" class="form-check-input" type="checkbox" name="input_semua_kolom" value="1" @checked($b->input_semua_kolom) aria-label="Semua kolom bebas diisi"></td>
                    <td class="text-end"><button form="fb-{{ $b->id }}" class="btn btn-sm btn-dms">Simpan</button></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
</div>
</div>

<div class="modal fade" id="modalKendaraan" tabindex="-1" aria-hidden="true" aria-labelledby="mkTitle">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" id="formKendaraan" action="{{ route('master.kendaraan.store') }}">
        @csrf <input type="hidden" name="_method" value="POST" id="mkMethod">
        <div class="modal-header"><h2 class="modal-title h5" id="mkTitle">Tambah Kendaraan</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
        <div class="modal-body">
            <div class="row g-2 mb-3">
                <div class="col"><label class="form-label" for="mkBidang">Bidang</label>
                    <select class="form-select" id="mkBidang" required><option value="" disabled selected>Pilih</option>
                        @foreach ($bidangList as $b)<option value="{{ $b->id }}">{{ $b->nama_tampil }}</option>@endforeach</select></div>
                <div class="col"><label class="form-label" for="mkJenis">Jenis</label>
                    <select class="form-select" id="mkJenis" name="jenis_kendaraan_id" required><option value="" disabled selected>Pilih bidang dulu</option></select></div>
            </div>
            <div class="mb-3"><label class="form-label" for="mkNomor">Nomor</label>
                <input class="form-control" id="mkNomor" name="nomor_kendaraan" maxlength="50" required placeholder="W 9242 UM"></div>
            <div class="mb-3"><label class="form-label" for="mkMulai">Mulai beroperasi</label>
                <input type="date" class="form-control" id="mkMulai" name="aktif_sejak" required>
                <div class="form-text">Kendaraan muncul di form input mulai tanggal ini.</div></div>
            <div class="mb-1"><label class="form-label" for="mkKet">Keterangan <span class="text-secondary small">(opsional)</span></label>
                <input class="form-control" id="mkKet" name="keterangan" maxlength="255" placeholder="Mis. unit sewa, pengganti W 1234 XY"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-dms">Simpan</button></div>
    </form></div>
</div>
<div class="modal fade" id="modalNonaktif" tabindex="-1" aria-hidden="true" aria-labelledby="mnTitle">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" id="formNonaktif">
        @csrf @method('PATCH') <input type="hidden" name="aksi" value="nonaktif">
        <div class="modal-header"><h2 class="modal-title h5" id="mnTitle">Nonaktifkan kendaraan</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
        <div class="modal-body">
            <p class="mb-3"><strong id="mnNomor"></strong> tidak akan muncul lagi di form input mulai tanggal di bawah. Riwayat sebelumnya tetap ada di dashboard dan laporan.</p>
            <div class="mb-3"><label class="form-label" for="mnTanggal">Nonaktif mulai</label>
                <input type="date" class="form-control" id="mnTanggal" name="nonaktif_sejak" required max="{{ now()->toDateString() }}">
                <div class="form-text" id="mnInfo"></div></div>
            <div class="mb-1"><label class="form-label" for="mnKet">Alasan <span class="text-secondary small">(opsional)</span></label>
                <input class="form-control" id="mnKet" name="keterangan" maxlength="255" placeholder="Misal: dijual, rusak berat, dipindah ke cabang lain"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-dms">Nonaktifkan</button></div>
    </form></div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const jenis = @json($jenisOptions);
    const base = @json(url('master/kendaraan'));
    const bSel = document.getElementById('mkBidang'), jSel = document.getElementById('mkJenis');
    const fillJenis = (bid, sel) => {
        jSel.innerHTML = '<option value="" disabled selected>Pilih</option>' + (jenis[bid] || []).map((j) => `<option value="${j.id}">${DMS.esc(j.nama)}</option>`).join('');
        if (sel) jSel.value = sel;
    };
    bSel.addEventListener('change', () => fillJenis(bSel.value));
    document.getElementById('modalKendaraan').addEventListener('show.bs.modal', (e) => {
        const b = e.relatedTarget, edit = b?.dataset.mode === 'edit', f = document.getElementById('formKendaraan');
        document.getElementById('mkTitle').textContent = edit ? 'Ubah Kendaraan' : 'Tambah Kendaraan';
        f.action = edit ? `${base}/${b.dataset.id}` : base;
        document.getElementById('mkMethod').value = edit ? 'PUT' : 'POST';
        bSel.value = edit ? b.dataset.bidang : '';
        fillJenis(bSel.value, edit ? b.dataset.jenis : null);
        document.getElementById('mkNomor').value = edit ? b.dataset.nomor : '';
        document.getElementById('mkKet').value = edit ? (b.dataset.ket || '') : '';
        document.getElementById('mkMulai').value = edit ? (b.dataset.mulai || '') : @json(now()->toDateString());
    });

    // Nonaktifkan: tanggal default hari ini, info data terakhir
    const fmt = (iso) => iso ? new Date(iso + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '';
    document.getElementById('modalNonaktif').addEventListener('show.bs.modal', (e) => {
        const b = e.relatedTarget;
        document.getElementById('formNonaktif').action = `${base}/${b.dataset.id}/status`;
        document.getElementById('mnNomor').textContent = b.dataset.nomor;
        const t = document.getElementById('mnTanggal');
        t.value = @json(now()->toDateString());
        if (b.dataset.mulai) {
            const d = new Date(b.dataset.mulai + 'T00:00:00'); d.setDate(d.getDate() + 1);
            t.min = d.toISOString().slice(0, 10);
        }
        document.getElementById('mnInfo').textContent = b.dataset.terakhir ? `Data terakhir: ${fmt(b.dataset.terakhir)}. Tanggal sebelum "nonaktif mulai" masih bisa diisi bila ada yang terlewat.` : 'Belum ada data untuk kendaraan ini.';
        document.getElementById('mnKet').value = '';
    });

    // Konfirmasi sebelum menyimpan (alert standar DMS v2)
    const namaOpsi = (sel) => sel.selectedIndex > 0 ? sel.options[sel.selectedIndex].text : '';
    document.getElementById('formKendaraan').dmsConfirm = () => {
        const ubah = document.getElementById('mkMethod').value === 'PUT';
        const nomor = document.getElementById('mkNomor').value.trim();
        return ubah
            ? { title: 'Simpan perubahan?', ok: 'Simpan', icon: 'fa-truck', text: `Data kendaraan ${nomor} akan diperbarui.` }
            : { title: 'Tambah kendaraan?', ok: 'Tambah', icon: 'fa-truck',
                text: `${nomor} (${namaOpsi(jSel)}, ${namaOpsi(bSel)}) akan muncul di form input mulai ${fmt(document.getElementById('mkMulai').value)}.` };
    };
    document.getElementById('formNonaktif').dmsConfirm = () => ({
        title: 'Nonaktifkan kendaraan?', ok: 'Nonaktifkan', tone: 'warning', icon: 'fa-power-off',
        text: `${document.getElementById('mnNomor').textContent} tidak muncul lagi di form input mulai ${fmt(document.getElementById('mnTanggal').value)}.`,
    });
    document.querySelectorAll('.f-jenis-ubah').forEach((f) => f.dmsConfirm = () => {
        const baru = f.elements.nama.value.trim();
        if (baru === f.dataset.lama) { DMS.toast('Nama jenis tidak berubah.', 'info', 2500, { title: 'Tidak ada perubahan' }); return false; }
        return { title: 'Ganti nama jenis?', ok: 'Simpan', icon: 'fa-tag', text: `"${f.dataset.lama}" diganti menjadi "${baru}".` };
    });
    document.querySelectorAll('.f-jenis-tambah').forEach((f) => f.dmsConfirm = () => ({
        title: 'Tambah jenis?', ok: 'Tambah', icon: 'fa-tag', text: `Jenis "${f.elements.nama.value.trim()}" ditambahkan ke ${f.dataset.bidang}.`,
    }));

    // Filter: langsung terapkan saat pilihan diubah; jenis menyesuaikan bidang
    const jenisPerBidang = @json($jenisPerBidang);
    const semuaJenis = @json($jenisList);
    const fb = document.getElementById('fb'), fj = document.getElementById('fj');
    const isiJenis = () => {
        const daftar = fb.value ? (jenisPerBidang[fb.value] || []) : semuaJenis;
        const pilih = fj.value;
        fj.innerHTML = '<option value="">Semua</option>' + daftar.map((j) => `<option value="${DMS.esc(j)}">${DMS.esc(j)}</option>`).join('');
        fj.value = daftar.includes(pilih) ? pilih : '';
    };
    isiJenis();
    fj.value = @json($f['jenis']);
    fb.addEventListener('change', isiJenis);
    document.querySelectorAll('#fKendaraan select.auto').forEach((el) => el.addEventListener('change', () => el.form.submit()));
    @if ($errors->any() && old('nomor_kendaraan'))
        new bootstrap.Modal('#modalKendaraan').show();
    @endif
})();
</script>
@endpush
