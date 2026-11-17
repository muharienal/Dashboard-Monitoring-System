@extends('layouts.app')
@section('title', 'Availability')
@section('content')
<div class="page-head"><div><h1>Availability</h1><div class="sub" id="lbl">–</div></div>
    @include('partials.filter', ['fields' => ['bidang', 'tahun', 'bulan', 'hari'], 'exportable' => true])
</div>

<div class="card-dms mb-3">
    <div class="card-body">
        <div class="row g-4 align-items-center">
            <div class="col-md-4 col-lg-3">
                <div class="chart-box short"><canvas id="cTotal" role="img" aria-label="Komposisi jam semua jenis"></canvas><div class="donut-center"><span class="v" id="arTotal">–</span><span class="l">Availability</span></div></div>
            </div>
            <div class="col-md-8 col-lg-6">
                <h2 class="h6 fw-bold mb-2">Total jam</h2>
                <div class="legend-inline" id="jamTotal"></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3" id="grid"></div>
@include('partials.dash-scripts', ['page' => ['module' => 'availability', 'url' => route('api.dashboard.availability')]])
@endsection
