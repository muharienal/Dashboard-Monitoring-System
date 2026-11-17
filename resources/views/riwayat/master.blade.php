@extends('layouts.app')
@section('title', 'Riwayat')
@use('App\Support\RiwayatMaster')
@php
    $adaFilter = collect($f)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    $warnaAksi = ['tambah' => 'success', 'hapus' => 'danger', 'nonaktif' => 'warning', 'aktif' => 'success', 'password' => 'info', 'keluar' => 'secondary', 'reset_tolak' => 'secondary', 'ubah' => 'light'];
@endphp
@section('content')
<div class="page-head">
    <div><h1>Riwayat</h1><div class="sub">Perubahan kendaraan, jenis, bidang, dan akun.</div></div>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnExportRiwayat" @disabled($riwayat->total() === 0)><i class="fas fa-file-excel"></i> Ekspor Excel</button>
</div>
@include('riwayat._tabs', ['tab' => 'master'])

<form class="filter-bar filter-block" method="GET" id="fRiwayat">
    <input type="hidden" name="tab" value="master">
    <div class="flex-grow-1" style="min-width:170px"><label class="form-label" for="mq">Nama / nomor</label>
        <input type="search" class="form-control form-control-sm" id="mq" name="q" value="{{ $f['q'] }}" placeholder="Mis. W 9242 atau nama akun"></div>
    <div><label class="form-label" for="mo">Data</label>
        <select class="form-select form-select-sm auto" id="mo" name="objek"><option value="">Semua</option>
            @foreach (RiwayatMaster::OBJEK as $k => $v)<option value="{{ $k }}" @selected($f['objek'] === $k)>{{ $v }}</option>@endforeach
        </select></div>
    <div><label class="form-label" for="ma">Aksi</label>
        <select class="form-select form-select-sm auto" id="ma" name="aksi"><option value="">Semua</option>
            @foreach (RiwayatMaster::AKSI as $k => $v)<option value="{{ $k }}" @selected($f['aksi'] === $k)>{{ $v }}</option>@endforeach
        </select></div>
    <div><label class="form-label" for="mb">Oleh</label>
        <select class="form-select form-select-sm auto" id="mb" name="oleh"><option value="">Semua</option>
            @foreach ($pengubah as $u)<option value="{{ $u->id }}" @selected($f['oleh'] === $u->id)>{{ $u->name }}</option>@endforeach
        </select></div>
    <div><label class="form-label" for="md">Dari</label>
        <input type="date" class="form-control form-control-sm auto" id="md" name="dari" value="{{ $f['dari'] }}" max="{{ now()->toDateString() }}"></div>
    <div><label class="form-label" for="ms">sampai</label>
        <input type="date" class="form-control form-control-sm auto" id="ms" name="sampai" value="{{ $f['sampai'] }}" max="{{ now()->toDateString() }}"></div>
    <div class="d-flex gap-2"><button class="btn btn-sm btn-dms"><i class="fas fa-search"></i> Cari</button>
        @if ($adaFilter)<a href="{{ route('riwayat.index', ['tab' => 'master']) }}" class="btn btn-sm btn-outline-secondary">Reset</a>@endif</div>
</form>

<div class="table-wrap">
    <table class="table table-dms table-hover mb-0 tbl-riwayat">
        <thead><tr>
            <th class="text-end" style="width:56px">No</th><th>Waktu</th><th>Oleh</th><th>Data</th><th>Nama</th><th>Aksi</th><th>Perubahan</th>
        </tr></thead>
        <tbody>
        @forelse ($riwayat as $i => $r)
            @php($waktu = \Illuminate\Support\Carbon::parse($r->created_at))
            <tr>
                <td class="text-end num text-secondary">{{ $riwayat->firstItem() + $i }}</td>
                <td class="text-nowrap"><span title="{{ $waktu->translatedFormat('l, j F Y H:i:s') }}">{{ $waktu->translatedFormat('j M Y') }}</span><div class="small text-secondary">{{ $waktu->format('H:i') }}</div></td>
                <td>{{ $r->user_nama ?? '–' }}@if (! $r->user_id && $r->user_nama)<div class="small text-secondary">akun dihapus</div>@endif</td>
                <td>{{ RiwayatMaster::OBJEK[$r->objek] ?? $r->objek }}</td>
                <td class="fw-bold">@if ($r->objek === 'kendaraan' && isset($kendaraanAda[$r->objek_id]))<a class="nomor-link" href="{{ route('kendaraan.profil', $r->objek_id) }}">{{ $r->objek_nama }}</a>@else{{ $r->objek_nama }}@endif</td>
                <td class="text-nowrap"><span class="badge aksi-{{ $warnaAksi[$r->aksi] ?? 'light' }}">{{ RiwayatMaster::AKSI[$r->aksi] ?? $r->aksi }}</span></td>
                <td>
                    <div class="chg-list">
                        @foreach ($r->ubah as $p)
                            <span class="chg">{{ $p['label'] }}
                                @if ($r->aksi === 'tambah')<b>{{ $p['ke'] }}</b>
                                @elseif ($r->aksi === 'hapus')<b>{{ $p['dari'] }}</b>
                                @else<s>{{ $p['dari'] ?? '–' }}</s><i class="fas fa-arrow-right arr" aria-label="menjadi"></i><b>{{ $p['ke'] ?? '–' }}</b>@endif
                            </span>
                        @endforeach
                        @if ($r->catatan)<span class="small text-secondary">{{ $r->catatan }}</span>@endif
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty-state">
                @if ($adaFilter)Tidak ada perubahan yang cocok dengan filter.@else Belum ada perubahan data master tercatat.@endif
            </td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
    <div>{{ $riwayat->links('pagination::bootstrap-5') }}</div>
    @if ($riwayat->total())<div class="small text-secondary">{{ number_format($riwayat->total(), 0, ',', '.') }} perubahan</div>@endif
</div>
@endsection

@push('vendor')
<script src="{{ asset('assets/vendor/xlsx/xlsx.full.min.js') }}" defer></script>
@endpush
@push('scripts')
<script>
(function () {
    const f = document.getElementById('fRiwayat');
    f.querySelectorAll('.auto').forEach((el) => el.addEventListener('change', () => f.submit()));
    document.getElementById('btnExportRiwayat').addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        const p = new URLSearchParams(new FormData(f)); p.set('format', 'xlsx');
        btn.disabled = true;
        try {
            const r = await DMS.fetchJson(@json(route('riwayat.index')) + '?' + p.toString());
            DMS.exportXlsx('Riwayat data master ' + new Date().toISOString().slice(0, 10), 'Riwayat master', r.aoa);
        } catch (err) {
            if (err.message !== 'unauthenticated') DMS.toast(DMS.esc(err.message), 'danger', 6000, { title: 'Gagal ekspor' });
        } finally { btn.disabled = false; }
    });
})();
</script>
@endpush
