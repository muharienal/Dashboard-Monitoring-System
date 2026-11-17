@extends('layouts.app')
@section('title', 'Perbandingan Bidang')
@use('App\Http\Controllers\PerbandinganController')
@php
    $p1 = fn ($v) => $v === null ? '–' : number_format((float) $v, 1, ',', '.').'%';
    $delta = function ($v, $label) {
        if ($v === null) {
            return '<span class="pb-delta netral">–</span>';
        }
        if ($v == 0) {
            return '<span class="pb-delta netral" title="sama dengan '.e($label).'">= 0</span>';
        }
        $naik = $v > 0;

        return '<span class="pb-delta '.($naik ? 'baik' : 'buruk').'" title="dibanding '.e($label).'"><span aria-hidden="true">'.($naik ? '▲' : '▼').'</span><span class="visually-hidden">'.($naik ? 'naik' : 'turun').'</span> '.number_format(abs($v), 1, ',', '.').'</span>';
    };
    $urlUrut = fn ($u) => route('dashboard.perbandingan', ['tahun' => $tahun, 'bulan' => $bulan, 'urut' => $u]);
@endphp
@section('content')
<div class="page-head">
    <div><h1>Perbandingan Bidang</h1><div class="sub">{{ $awal->translatedFormat('F Y') }}, dibanding {{ $labelLalu }}</div></div>
    <form class="filter-bar no-print" method="GET" id="fPb">
        <input type="hidden" name="urut" value="{{ $urut }}">
        <div><label class="form-label" for="pbT">Tahun</label>
            <select class="form-select form-select-sm" id="pbT" name="tahun" style="min-width:96px">@foreach ($tahunList as $t)<option value="{{ $t }}" @selected($t === $tahun)>{{ $t }}</option>@endforeach</select></div>
        <div><label class="form-label" for="pbB">Bulan</label>
            <select class="form-select form-select-sm" id="pbB" name="bulan">@foreach (range(1, 12) as $m)<option value="{{ $m }}" @selected($m === $bulan)>{{ \Carbon\CarbonImmutable::create(2000, $m, 1)->translatedFormat('F') }}</option>@endforeach</select></div>
    </form>
</div>

<div class="card-dms mb-3">
    <div class="card-head flex-wrap gap-2"><h2>Peringkat</h2>
        <nav class="seg" aria-label="Urutkan menurut">
            @foreach (PerbandinganController::URUT as $k => $l)<a href="{{ $urlUrut($k) }}" class="{{ $urut === $k ? 'active' : '' }}" @if ($urut === $k) aria-current="true" @endif>{{ $l }}</a>@endforeach
        </nav>
    </div>
    <div class="card-body p-0">
        <div class="table-wrap border-0">
            <table class="table table-dms mb-0 tbl-peringkat">
                <thead><tr>
                    <th class="text-center" style="width:56px">#</th><th>Bidang</th><th class="text-end">Unit</th>
                    @foreach (PerbandinganController::URUT as $k => $l)<th class="{{ $urut === $k ? 'aktif' : '' }}">{{ $l }}</th>@endforeach
                </tr></thead>
                <tbody>
                @foreach ($baris as $i => $b)
                    <tr>
                        <td class="text-center"><span class="pb-rank {{ $b[$urut] === null ? 'kosong' : '' }}">{{ $b[$urut] === null ? '–' : $i + 1 }}</span></td>
                        <td><a class="fw-bold nomor-link" href="{{ route('dashboard.summary', $b['kode']) }}">{{ $b['nama'] }}</a>
                            @if ($b['kosong'])<div class="small kl-curiga mt-1"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> {{ $b['kosong'] }} tanggal tanpa unit Jalan/Rusak</div>@endif</td>
                        <td class="text-end num">{{ number_format($b['unit'], 0, ',', '.') }}</td>
                        @foreach (['availability', 'utilisasi', 'kelengkapan'] as $k)
                            <td class="{{ $urut === $k ? 'aktif' : '' }}">
                                <div class="pb-nilai"><span class="v">{{ $p1($b[$k]) }}</span>{!! $delta($b['d_'.$k], $k === 'kelengkapan' ? \Carbon\CarbonImmutable::create($tahun, $bulan, 1)->subMonthNoOverflow()->translatedFormat('F') : ($b['label_lalu'] ?? '')) !!}</div>
                                <span class="pb-bar m-{{ $k }}" aria-hidden="true"><span style="width:{{ min(100, max(0, $b[$k] ?? 0)) }}%"></span></span>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="pb-ket">▲▼ = selisih poin persen.</div>
    </div>
</div>

<div class="card-dms">
    <div class="card-head flex-wrap gap-2"><h2>Kalender kelengkapan input</h2>
        <div class="legend-row">
            <span><span class="legend-dot kk-lengkap"></span>Lengkap</span><span><span class="legend-dot kk-sebagian"></span>Sebagian</span>
            <span><span class="legend-dot kk-kosong"></span>Belum diisi</span><span><span class="legend-dot kk-curiga"></span>Tanpa unit Jalan/Rusak</span>
        </div>
    </div>
    <div class="card-body">
        <div class="kalender-gulir">
            <table class="kk-tabel" aria-label="Kelengkapan input per tanggal per bidang">
                <thead><tr><th scope="col" class="kk-nama">Bidang</th>
                    @foreach ($kalender[0]['sel'] ?? [] as $s)
                        @php $hr = \Carbon\CarbonImmutable::parse($s['tanggal']); @endphp
                        <th scope="col" class="{{ $hr->isWeekend() ? 'libur' : '' }}" title="{{ $hr->translatedFormat('l, j F') }}">{{ $s['hari'] }}</th>
                    @endforeach
                </tr></thead>
                <tbody>
                @foreach ($kalender as $row)
                    <tr><th scope="row" class="kk-nama">{{ $row['nama'] }}</th>
                        @foreach ($row['sel'] as $s)
                            @php
                                $judul = \Carbon\CarbonImmutable::parse($s['tanggal'])->translatedFormat('j F').' · '.$row['nama'].': '
                                    .match ($s['status']) { 'nanti' => 'belum waktunya', 'tanpa' => 'tidak ada unit beroperasi',
                                        default => $s['terisi'].' dari '.$s['unit'].' unit terisi' }
                                    .($s['curiga'] ? ' — tidak ada unit Jalan/Rusak' : '');
                                $kelas = 'kk-sel kk-'.$s['status'].($s['curiga'] ? ' kk-curiga' : '');
                                $bisaKlik = $row['bisa_input'] && in_array($s['status'], ['lengkap', 'sebagian', 'kosong'], true);
                            @endphp
                            <td>
                                @if ($bisaKlik)
                                    <a class="{{ $kelas }}" href="{{ route('input.show', ['bidang' => $row['bidang'], 'tanggal' => $s['tanggal']]) }}" title="{{ $judul }}" aria-label="{{ $judul }}"></a>
                                @else
                                    <span class="{{ $kelas }}" title="{{ $judul }}" aria-label="{{ $judul }}"></span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('#fPb select').forEach((s) => s.addEventListener('change', () => s.form.submit()));
</script>
@endpush
