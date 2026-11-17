@extends('layouts.app')
@section('title', 'Riwayat')
@php
    // Bidang yang dikunci untuk petugas bidang tidak dihitung sebagai filter pilihan pengguna.
    $adaFilter = collect($f)->except($batas !== null ? ['bidang'] : [])->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    $warna = ['jalan' => 'JALAN', 'standby' => 'STANDBY', 'rusak' => 'RUSAK', 'perbaikan' => 'PERBAIKAN', 'pm' => 'PM'];
    $tglPanjang = fn ($d) => \Illuminate\Support\Carbon::parse($d)->translatedFormat('j M Y');
@endphp
@section('content')
<div class="page-head">
    <div><h1>Riwayat</h1><div class="sub">Siapa mengubah angka input, dari berapa ke berapa, dan kapan.</div></div>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnExportRiwayat" @disabled($riwayat->total() === 0)><i class="fas fa-file-excel"></i> Ekspor Excel</button>
</div>
@if (auth()->user()->isAdmin())
    @include('riwayat._tabs', ['tab' => 'input'])
@elseif ($batas !== null)
    <p class="text-secondary small mb-3"><i class="fas fa-circle-info" aria-hidden="true"></i> Menampilkan riwayat bidang {{ $bidangList->first()?->nama_tampil }} saja.</p>
@endif

<form class="filter-bar filter-block" method="GET" id="fRiwayat">
    <div class="flex-grow-1" style="min-width:170px"><label class="form-label" for="rq">Nomor kendaraan</label>
        <input type="search" class="form-control form-control-sm" id="rq" name="q" value="{{ $f['q'] }}" placeholder="Mis. W 9242"></div>
    <div><label class="form-label" for="rb">Bidang</label>
        <select class="form-select form-select-sm auto" id="rb" name="bidang" @disabled($batas !== null)>@if ($batas === null)<option value="">Semua</option>@endif
            @foreach ($bidangList as $b)<option value="{{ $b->kode }}" @selected($f['bidang'] === $b->kode)>{{ $b->nama_tampil }}</option>@endforeach
        </select></div>
    <div><label class="form-label" for="ro">Oleh</label>
        <select class="form-select form-select-sm auto" id="ro" name="oleh"><option value="">Semua</option>
            @foreach ($pengubah as $u)<option value="{{ $u->id }}" @selected($f['oleh'] === $u->id)>{{ $u->name }}</option>@endforeach
        </select></div>
    <div><label class="form-label" for="ra">Jenis</label>
        <select class="form-select form-select-sm auto" id="ra" name="aksi"><option value="">Semua</option>
            <option value="ubah" @selected($f['aksi'] === 'ubah')>Diubah</option><option value="buat" @selected($f['aksi'] === 'buat')>Data baru</option>
        </select></div>
    <div><label class="form-label" for="rt">Tanggal data</label>
        <input type="date" class="form-control form-control-sm auto" id="rt" name="tanggal" value="{{ $f['tanggal'] }}" max="{{ now()->toDateString() }}"></div>
    <div><label class="form-label" for="rd">Diubah dari</label>
        <input type="date" class="form-control form-control-sm auto" id="rd" name="dari" value="{{ $f['dari'] }}" max="{{ now()->toDateString() }}"></div>
    <div><label class="form-label" for="rs">sampai</label>
        <input type="date" class="form-control form-control-sm auto" id="rs" name="sampai" value="{{ $f['sampai'] }}" max="{{ now()->toDateString() }}"></div>
    <div class="d-flex gap-2"><button class="btn btn-sm btn-dms"><i class="fas fa-search"></i> Cari</button>
        @if ($adaFilter)<a href="{{ route('riwayat.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>@endif</div>
</form>

<div class="table-wrap">
    <table class="table table-dms table-hover mb-0 tbl-riwayat">
        <thead><tr>
            <th class="text-end" style="width:56px">No</th><th>Waktu</th><th>Oleh</th><th>Bidang</th><th>Tanggal data</th><th>Nomor</th><th>Perubahan</th>
        </tr></thead>
        <tbody>
        @forelse ($riwayat as $i => $r)
            @php($waktu = \Illuminate\Support\Carbon::parse($r->created_at))
            <tr>
                <td class="text-end num text-secondary">{{ $riwayat->firstItem() + $i }}</td>
                <td class="text-nowrap"><span title="{{ $waktu->translatedFormat('l, j F Y H:i:s') }}">{{ $waktu->translatedFormat('j M Y') }}</span><div class="small text-secondary">{{ $waktu->format('H:i') }}</div></td>
                <td>{{ $r->user_nama ?? '–' }}@if (! $r->user_id && $r->user_nama)<div class="small text-secondary">akun dihapus</div>@endif</td>
                <td>{{ $r->bidang ?? '–' }}</td>
                <td class="text-nowrap"><a href="{{ route('riwayat.index', ['tanggal' => $r->tanggal, 'q' => $r->nomor_kendaraan]) }}" title="Semua perubahan unit ini di tanggal tsb">{{ $tglPanjang($r->tanggal) }}</a></td>
                <td class="fw-bold text-nowrap">@if ($r->kendaraan_id)<a class="nomor-link" href="{{ route('kendaraan.profil', $r->kendaraan_id) }}">{{ $r->nomor_kendaraan }}</a>@else{{ $r->nomor_kendaraan }}@endif</td>
                <td>
                    <div class="chg-list">
                        @if ($r->aksi === 'buat')<span class="badge text-bg-light border text-secondary">Data baru</span>@endif
                        @foreach ($r->ubah as $p)
                            <span class="chg"><i class="dot" style="background:{{ config('dms.warna.'.$warna[$p['kolom']]) }}" aria-hidden="true"></i>{{ $p['label'] }}
                                @if ($p['dari'] !== null)<s>{{ $p['dari'] }}</s><i class="fas fa-arrow-right arr" aria-label="menjadi"></i>@endif<b>{{ $p['ke'] }}</b></span>
                        @endforeach
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty-state">
                @if ($adaFilter)Tidak ada perubahan yang cocok dengan filter.@else Belum ada perubahan tercatat.@endif
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
            DMS.exportXlsx('Riwayat perubahan ' + new Date().toISOString().slice(0, 10), 'Riwayat', r.aoa);
        } catch (err) {
            if (err.message !== 'unauthenticated') DMS.toast(DMS.esc(err.message), 'danger', 6000, { title: 'Gagal ekspor' });
        } finally { btn.disabled = false; }
    });
})();
</script>
@endpush
