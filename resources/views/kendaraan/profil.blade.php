@extends('layouts.app')
@section('title', $k->nomor_kendaraan)
@use('App\Support\RiwayatMaster')
@php
    $warna = config('dms.warna');
    $LABEL = ['jalan' => 'Jalan', 'standby' => 'Standby', 'rusak' => 'Rusak', 'perbaikan' => 'Perbaikan', 'pm' => 'PM'];
    $W = ['jalan' => $warna['JALAN'], 'standby' => $warna['STANDBY'], 'rusak' => $warna['RUSAK'], 'perbaikan' => $warna['PERBAIKAN'], 'pm' => $warna['PM']];
    $n0 = fn ($v) => number_format((float) $v, 0, ',', '.');
    $p1 = fn ($v) => $v === null ? '–' : number_format((float) $v, 1, ',', '.').'%';
    $tgl = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->translatedFormat('j M Y') : '–';
    $sebelum = $awal->subDay()->toDateString();
    $sesudah = $akhir->addDays(\App\Http\Controllers\ProfilKendaraanController::HARI);
@endphp
@section('content')
<div class="page-head">
    <div>
        <h1>{{ $k->nomor_kendaraan }}</h1>
        <div class="sub">{{ $k->jenis?->nama }} · {{ $bidang?->nama_tampil }}
            · @if ($k->is_active)<span class="badge text-bg-success">Aktif</span>@else<span class="badge text-bg-secondary">Nonaktif sejak {{ $tgl($k->nonaktif_sejak) }}</span>@endif
        </div>
    </div>
    <div class="page-actions">
        @if ($bidang)<a class="btn btn-sm btn-outline-secondary" href="{{ route('dashboard.summary', $bidang) }}"><i class="fas fa-table-cells-large"></i> {{ $bidang->nama_tampil }}</a>@endif
        @if ($bisaInput)<a class="btn btn-sm btn-gold" href="{{ route('input.show', $bidang) }}"><i class="fas fa-pen-to-square"></i> Input data</a>@endif
    </div>
</div>

<div class="profil-info">
    <span><span class="l">Mulai beroperasi</span>{{ $tgl($k->aktif_sejak) }}</span>
    <span><span class="l">Data pertama</span>{{ $tgl($rentangData->a) }}</span>
    <span><span class="l">Data terakhir</span>{{ $tgl($rentangData->b) }}</span>
    <span><span class="l">Total hari berisi data</span>{{ $n0($rentangData->n) }} hari</span>
    @if ($k->keterangan)<span><span class="l">Keterangan</span>{{ $k->keterangan }}</span>@endif
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
    <h2 class="h6 mb-0 fw-bold">{{ $awal->translatedFormat('j M Y') }} – {{ $akhir->translatedFormat('j M Y') }} <span class="text-secondary fw-normal">({{ \App\Http\Controllers\ProfilKendaraanController::HARI }} hari)</span></h2>
    <nav class="btn-group btn-group-sm" aria-label="Pindah periode">
        <a class="btn btn-outline-secondary" href="{{ route('kendaraan.profil', ['kendaraan' => $k->id, 'akhir' => $sebelum]) }}"><i class="fas fa-chevron-left"></i> Sebelumnya</a>
        @if ($akhir->lessThan($hariIni))
            <a class="btn btn-outline-secondary" href="{{ route('kendaraan.profil', ['kendaraan' => $k->id, 'akhir' => $sesudah->greaterThan($hariIni) ? null : $sesudah->toDateString()]) }}">Berikutnya <i class="fas fa-chevron-right"></i></a>
            <a class="btn btn-outline-secondary" href="{{ route('kendaraan.profil', $k->id) }}">Hari ini</a>
        @endif
    </nav>
</div>

<div class="kpi-strip">
    <div class="kpi"><div class="kpi-label">Availability</div><div class="kpi-value">{{ $p1($ar) }}</div><div class="kpi-sub">(jalan + standby) ÷ total jam</div></div>
    <div class="kpi"><div class="kpi-label">Utilisasi</div><div class="kpi-value">{{ $p1($utilisasi) }}</div><div class="kpi-sub">hari berstatus Jalan</div></div>
    <div class="kpi"><div class="kpi-label">Hari berisi data</div><div class="kpi-value">{{ $n0($hariData) }}<span class="kpi-dari"> / {{ $n0($seharusnya) }}</span></div></div>
    <div class="kpi"><div class="kpi-label">Jam perbaikan</div><div class="kpi-value">{{ $n0($jam['perbaikan']) }}</div><div class="kpi-sub">di {{ $n0($gangguan->where('perbaikan', '>', 0)->count()) }} hari</div></div>
</div>

<div class="card-dms mb-3">
    <div class="card-head"><h2>Kalender status</h2><span class="hint">warna = status dengan jam terbanyak hari itu</span></div>
    <div class="card-body">
        <div class="kalender-gulir">
            <div class="kalender" role="grid" aria-label="Status harian {{ $k->nomor_kendaraan }}">
                <div class="kal-hari" aria-hidden="true"><span></span>@foreach (['Sen', '', 'Rab', '', 'Jum', '', 'Min'] as $h)<span>{{ $h }}</span>@endforeach</div>
                @foreach ($minggu as $i => $m)
                    <div class="kal-minggu" role="row">
                        @php $labelBulan = $i === 0 || $m['awal']->month !== $minggu[$i - 1]['awal']->month ? $m['awal']->translatedFormat('M') : ''; @endphp
                        <span class="kal-bulan" aria-hidden="true">{{ $labelBulan }}</span>
                        @foreach ($m['hari'] as $h)
                            @php $s = $h['sel']; @endphp
                            @if (! $s)
                                <span class="kal-sel kosong-luar" aria-hidden="true"></span>
                            @else
                                @php
                                    $judul = \Illuminate\Support\Carbon::parse($h['tanggal'])->translatedFormat('l, j F Y').': ';
                                    $judul .= $s['nilai'] ? collect($s['nilai'])->filter()->map(fn ($v, $kk) => $LABEL[$kk].' '.$v.' jam')->join(', ').($s['oleh'] ? ' · diisi '.$s['oleh'] : '')
                                        : ($s['status'] === 'kosong' ? 'belum ada data' : 'tidak beroperasi');
                                    $gaya = isset($W[$s['status']]) ? 'background:'.$W[$s['status']] : '';
                                @endphp
                                @if ($bisaInput && $s['status'] !== 'luar')
                                    <a role="gridcell" class="kal-sel s-{{ $s['status'] }}" style="{{ $gaya }}" href="{{ route('input.show', ['bidang' => $bidang, 'tanggal' => $h['tanggal']]) }}" title="{{ $judul }}" aria-label="{{ $judul }}"></a>
                                @else
                                    <span role="gridcell" class="kal-sel s-{{ $s['status'] }}" style="{{ $gaya }}" title="{{ $judul }}" aria-label="{{ $judul }}"></span>
                                @endif
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
        @if ($total > 0)
            <div class="profil-komposisi mt-3" role="img" aria-label="Komposisi jam">
                <div class="stack">@foreach ($LABEL as $kk => $l)@if ($jam[$kk] > 0)<span style="width:{{ $jam[$kk] / $total * 100 }}%;background:{{ $W[$kk] }}" title="{{ $l }} {{ $n0($jam[$kk]) }} jam"></span>@endif @endforeach</div>
            </div>
        @endif
        <div class="legend-row mt-2">
            @foreach ($LABEL as $kk => $l)<span><span class="legend-dot" style="background:{{ $W[$kk] }}"></span>{{ $l }}@if ($total > 0) <b>{{ $n0($jam[$kk]) }} jam</b> <span class="text-secondary">({{ $p1($jam[$kk] / $total * 100) }})</span>@endif</span>@endforeach
            <span><span class="legend-dot s-kosong"></span>Belum ada data</span>
            <span><span class="legend-dot s-luar"></span>Tidak beroperasi</span>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="{{ $lihatRiwayat ? 'col-xl-6' : 'col-12' }}">
        <div class="card-dms h-100">
            <div class="card-head"><h2>Hari rusak, perbaikan & PM</h2><span class="hint">{{ $gangguan->count() }} hari</span></div>
            <div class="card-body p-0">
                <div class="table-wrap border-0" style="max-height:420px">
                    <table class="table table-dms table-sm mb-0 sticky-head">
                        <thead><tr><th>Tanggal</th><th class="text-end">Rusak</th><th class="text-end">Perbaikan</th><th class="text-end">PM</th><th>Diisi</th></tr></thead>
                        <tbody>
                        @forelse ($gangguan as $g)
                            <tr><td class="text-nowrap">{{ $g->tanggal->translatedFormat('D, j M Y') }}</td>
                                <td class="text-end num">{{ $g->rusak ?: '–' }}</td><td class="text-end num">{{ $g->perbaikan ?: '–' }}</td><td class="text-end num">{{ $g->pm ?: '–' }}</td>
                                <td class="small text-secondary">{{ $g->updater?->name ?? '–' }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="empty-state">Tidak ada hari rusak, perbaikan, atau PM pada periode ini.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @if ($lihatRiwayat)
    <div class="col-xl-6">
        <div class="card-dms h-100">
            <div class="card-head"><h2>Riwayat perubahan</h2>
                <a class="hint" href="{{ route('riwayat.index', ['q' => $k->nomor_kendaraan]) }}">Lihat semua</a></div>
            <div class="card-body">
                @if ($riwayatMaster->isEmpty() && $riwayatInput->isEmpty())
                    <div class="empty-state py-3">Belum ada perubahan tercatat untuk unit ini.</div>
                @endif
                <ul class="profil-riwayat">
                    @foreach ($riwayatMaster as $r)
                        <li><span class="w">{{ \Illuminate\Support\Carbon::parse($r->created_at)->translatedFormat('j M Y H:i') }}</span>
                            <span><b>{{ RiwayatMaster::AKSI[$r->aksi] ?? $r->aksi }}</b> oleh {{ $r->user_nama ?? '–' }}
                                @foreach (json_decode($r->perubahan ?? '[]', true) as $p)<span class="chg">{{ $p['label'] }} <s>{{ $p['dari'] ?? '–' }}</s><i class="fas fa-arrow-right arr" aria-label="menjadi"></i><b>{{ $p['ke'] ?? '–' }}</b></span>@endforeach
                            </span></li>
                    @endforeach
                    @foreach ($riwayatInput as $r)
                        @php $sb = $r->sebelum ? json_decode($r->sebelum, true) : null; @endphp
                        @php $ss = json_decode($r->sesudah, true); @endphp
                        <li><span class="w">{{ \Illuminate\Support\Carbon::parse($r->created_at)->translatedFormat('j M Y H:i') }}</span>
                            <span>Data {{ $tgl($r->tanggal) }} {{ $r->aksi === 'buat' ? 'diisi' : 'diubah' }} oleh {{ $r->user_nama ?? '–' }}
                                @foreach (['jalan' => 'Jalan', 'rusak' => 'Rusak', 'perbaikan' => 'Perbaikan', 'pm' => 'PM'] as $kk => $l)
                                    @if ($sb === null ? ($ss[$kk] ?? 0) > 0 : ($sb[$kk] ?? 0) !== ($ss[$kk] ?? 0))
                                        <span class="chg">{{ $l }} @if ($sb !== null)<s>{{ $sb[$kk] ?? 0 }}</s><i class="fas fa-arrow-right arr" aria-label="menjadi"></i>@endif<b>{{ $ss[$kk] ?? 0 }}</b></span>
                                    @endif
                                @endforeach
                            </span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
