@extends('layouts.app')
@section('title', 'Riwayat')
@use('App\Support\RiwayatLogin')
@php
    $adaFilter = collect($f)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    $warna = ['berhasil' => 'success', 'gagal' => 'danger', 'nonaktif' => 'danger', 'terkunci' => 'warning', 'keluar' => 'secondary', 'keluar_otomatis' => 'info'];
@endphp
@section('content')
<div class="page-head">
    <div><h1>Riwayat</h1><div class="sub">Siapa masuk dan keluar, kapan, dari perangkat apa.</div></div>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnExportRiwayat" @disabled($riwayat->total() === 0)><i class="fas fa-file-excel"></i> Ekspor Excel</button>
</div>
@include('riwayat._tabs', ['tab' => 'login'])

@if ($gagal24 >= 5)
    <div class="reset-notice mb-3"><i class="fas fa-shield-halved" aria-hidden="true"></i><span><b>{{ $gagal24 }} percobaan masuk gagal</b> dalam 24 jam terakhir. Periksa apakah ada yang mencoba menebak password.</span>
        <a class="go" href="{{ route('riwayat.index', ['tab' => 'login', 'hasil' => 'gagal']) }}">Lihat <i class="fas fa-chevron-right" aria-hidden="true"></i></a></div>
@endif

<form class="filter-bar filter-block" method="GET" id="fRiwayat">
    <input type="hidden" name="tab" value="login">
    <div class="flex-grow-1" style="min-width:170px"><label class="form-label" for="lq">Akun / IP</label>
        <input type="search" class="form-control form-control-sm" id="lq" name="q" value="{{ $f['q'] }}" placeholder="Nama, nama pengguna, atau IP"></div>
    <div><label class="form-label" for="lh">Hasil</label>
        <select class="form-select form-select-sm auto" id="lh" name="hasil"><option value="">Semua</option>
            @foreach (RiwayatLogin::HASIL as $k => $v)<option value="{{ $k }}" @selected($f['hasil'] === $k)>{{ $v }}</option>@endforeach
        </select></div>
    <div><label class="form-label" for="ld">Dari</label><input type="date" class="form-control form-control-sm auto" id="ld" name="dari" value="{{ $f['dari'] }}" max="{{ now()->toDateString() }}"></div>
    <div><label class="form-label" for="ls">sampai</label><input type="date" class="form-control form-control-sm auto" id="ls" name="sampai" value="{{ $f['sampai'] }}" max="{{ now()->toDateString() }}"></div>
    <div class="d-flex gap-2"><button class="btn btn-sm btn-dms"><i class="fas fa-search"></i> Cari</button>
        @if ($adaFilter)<a href="{{ route('riwayat.index', ['tab' => 'login']) }}" class="btn btn-sm btn-outline-secondary">Reset</a>@endif</div>
</form>

<div class="table-wrap">
    <table class="table table-dms table-hover mb-0 tbl-riwayat">
        <thead><tr><th class="text-end" style="width:56px">No</th><th>Waktu</th><th>Akun</th><th>Hasil</th><th>Perangkat</th><th>IP</th></tr></thead>
        <tbody>
        @forelse ($riwayat as $i => $r)
            @php $waktu = \Illuminate\Support\Carbon::parse($r->created_at); @endphp
            <tr>
                <td class="text-end num text-secondary">{{ $riwayat->firstItem() + $i }}</td>
                <td class="text-nowrap"><span title="{{ $waktu->translatedFormat('l, j F Y H:i:s') }}">{{ $waktu->translatedFormat('j M Y') }}</span><div class="small text-secondary">{{ $waktu->format('H:i:s') }}</div></td>
                <td>@if ($r->user_nama)<b>{{ $r->user_nama }}</b>@if (! $r->user_id)<div class="small text-secondary">akun dihapus</div>@endif
                    @else<span class="text-secondary">Tidak dikenal</span>@endif
                    @if ($r->isian && in_array($r->hasil, ['gagal', 'terkunci', 'nonaktif'], true))<div class="small text-secondary">diketik: {{ $r->isian }}</div>@endif</td>
                <td><span class="badge aksi-{{ $warna[$r->hasil] ?? 'light' }}">{{ RiwayatLogin::HASIL[$r->hasil] ?? $r->hasil }}</span></td>
                <td><span title="{{ $r->user_agent }}">{{ $r->perangkat ?? '–' }}</span></td>
                <td class="num">{{ $r->ip ?? '–' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty-state">@if ($adaFilter)Tidak ada yang cocok dengan filter.@else Belum ada riwayat login.@endif</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
    <div>{{ $riwayat->links('pagination::bootstrap-5') }}</div>
    @if ($riwayat->total())<div class="small text-secondary">{{ number_format($riwayat->total(), 0, ',', '.') }} catatan</div>@endif
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
        const btn = e.currentTarget, p = new URLSearchParams(new FormData(f)); p.set('format', 'xlsx');
        btn.disabled = true;
        try { const r = await DMS.fetchJson(@json(route('riwayat.index')) + '?' + p.toString()); DMS.exportXlsx('Riwayat login ' + new Date().toISOString().slice(0, 10), 'Login', r.aoa); }
        catch (err) { if (err.message !== 'unauthenticated') DMS.toast(DMS.esc(err.message), 'danger', 6000, { title: 'Gagal ekspor' }); }
        finally { btn.disabled = false; }
    });
})();
</script>
@endpush
