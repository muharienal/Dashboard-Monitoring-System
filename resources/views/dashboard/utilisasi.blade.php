@extends('layouts.app')
@section('title', 'Utilisasi')
@section('content')
<div class="page-head"><div><h1>Utilisasi</h1><div class="sub" id="lbl">–</div></div>
    @include('partials.filter', ['fields' => ['cari', 'bidang', 'jenis', 'tahun', 'bulan', 'hari'], 'exportable' => true, 'cariPlaceholder' => 'Jenis kendaraan'])
</div>

<div class="card-dms mb-3">
    <div class="card-head"><h2>Rata-rata unit per hari</h2><span class="hint">per jenis kendaraan</span></div>
    <div class="card-body"><div id="avgEmpty"></div><div class="chart-box"><canvas id="cAvg" role="img" aria-label="Rata-rata unit per hari per jenis"></canvas></div></div>
</div>

<div class="card-dms">
    <div class="card-body">
        <div class="table-wrap">
            <table class="table table-dms table-sm table-hover" id="tbl">
                <thead>
                    <tr><th rowspan="2" class="text-end" style="width:52px">No</th><th rowspan="2">Jenis</th><th rowspan="2" class="text-end">Unit</th><th colspan="3" class="text-center">Rata-rata unit per hari</th><th rowspan="2" class="text-end">Utilisasi</th></tr>
                    <tr><th class="text-end">Jalan</th><th class="text-end">Standby</th><th class="text-end">Rusak</th></tr>
                </thead>
                <tbody></tbody><tfoot class="table-light"></tfoot>
            </table>
        </div>
    </div>
</div>
@include('partials.dash-scripts', ['page' => ['module' => 'utilisasi', 'url' => route('api.dashboard.utilisasi')]])
@endsection
