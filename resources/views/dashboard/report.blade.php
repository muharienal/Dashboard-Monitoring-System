@extends('layouts.app')
@section('title', 'Detail Reports')
@section('content')
<div class="page-head"><div><h1>Detail Reports</h1><div class="sub">Jam per status per kendaraan. Pilih bulan untuk kolom per tanggal, atau "Semua Bulan" untuk kolom per bulan · <span id="lbl">–</span></div></div></div>
@include('partials.filter', ['fields' => ['bidang', 'jenis', 'tahun', 'bulan', 'hari'], 'exportable' => true])
<div class="card-dms"><div class="card-body" id="report"><div class="empty-state">Memuat…</div></div></div>
@include('partials.dash-scripts', ['page' => ['module' => 'report', 'url' => route('api.dashboard.report')]])
@endsection
