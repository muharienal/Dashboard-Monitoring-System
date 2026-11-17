@extends('layouts.app')
@section('title', $bidang->nama_tampil)
@section('content')
<div class="page-head">
    <div><h1>{{ $bidang->nama_tampil }}</h1><div class="sub" id="lbl">–</div></div>
    <div class="page-actions no-print">
        @include('partials.filter', ['fields' => ['tahun', 'bulan']])
        <a href="{{ route('dashboard.laporan', ['tab' => 'detail', 'bidang' => $bidang->kode]) }}" class="btn btn-sm btn-outline-secondary" id="linkDetail"><i class="fas fa-table"></i> Laporan detail</a>
        @if (auth()->user()->canInput($bidang))<a href="{{ route('input.show', $bidang) }}" class="btn btn-sm btn-gold"><i class="fas fa-pen-to-square"></i> Input data</a>@endif
    </div>
</div>

<div class="kpi-strip">
    <div class="kpi"><div class="kpi-label">Availability</div><div class="kpi-value" id="kAr">–</div><div class="kpi-delta" id="dkAr"></div></div>
    <div class="kpi"><div class="kpi-label">Utilisasi</div><div class="kpi-value" id="kUtil">–</div><div class="kpi-delta" id="dkUtil"></div></div>
    <div class="kpi"><div class="kpi-label">Unit jalan per hari</div><div class="kpi-value" id="kJalan">–</div><div class="kpi-delta" id="dkJalan"></div></div>
    <div class="kpi"><div class="kpi-label">Jam perbaikan</div><div class="kpi-value" id="kPerb">–</div><div class="kpi-delta" id="dkPerb"></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-6"><div class="card-dms h-100">
        <div class="card-head"><h2>Unit per bulan</h2><span class="hint">rata-rata per hari</span></div>
        <div class="card-body"><div id="ubEmpty"></div><div class="chart-box"><canvas id="cBulanan" role="img" aria-label="Rata-rata unit per bulan"></canvas></div></div>
    </div></div>
    <div class="col-xl-6"><div class="card-dms h-100">
        <div class="card-head"><h2 id="trenTitle">Unit per hari</h2></div>
        <div class="card-body"><div id="trEmpty"></div><div class="chart-box"><canvas id="cTren" role="img" aria-label="Tren unit"></canvas></div><div class="tren-catatan" id="trNote"></div></div>
    </div></div>
</div>

<div class="row g-3">
    <div class="col-xl-4"><div class="card-dms h-100">
        <div class="card-head"><h2>Komposisi jam</h2></div>
        <div class="card-body">
            <div class="chart-box short"><canvas id="cAvail" role="img" aria-label="Komposisi jam"></canvas><div class="donut-center"><span class="v" id="arCenter">–</span><span class="l">jam total</span></div></div>
            <div class="mt-3" id="availLegend"></div>
        </div>
    </div></div>
    <div class="col-xl-8"><div class="card-dms h-100">
        <div class="card-head"><h2>Perbaikan terbanyak</h2><span class="hint">{{ config('dms.top_n_maintenance') }} kendaraan teratas</span></div>
        <div class="card-body"><div id="mEmpty"></div><div class="chart-box"><canvas id="cMaint" role="img" aria-label="Jam perbaikan per kendaraan"></canvas></div></div>
    </div></div>
</div>
@include('partials.dash-scripts', ['page' => ['module' => 'summary', 'url' => route('api.dashboard.summary', $bidang), 'bidang' => $bidang->nama_tampil, 'kode' => $bidang->kode]])
@endsection
