@extends('layouts.app')
@section('title', 'Perbaikan')
@section('content')
<div class="page-head"><div><h1>Perbaikan</h1><div class="sub" id="lbl">–</div></div>
    @include('partials.filter', ['fields' => ['cari', 'jenis', 'tahun', 'bulan', 'hari'], 'exportable' => true])
</div>
<div class="row g-3" id="bidangCharts"></div>
@include('partials.dash-scripts', ['page' => ['module' => 'maintenance', 'url' => route('api.dashboard.maintenance')]])
@endsection
