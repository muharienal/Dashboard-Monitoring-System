@extends('layouts.app')
@section('title', 'General Reports')
@section('content')
<div class="page-head"><div><h1>General Reports</h1><div class="sub">Rekap jam, availability, dan utilisasi per bidang & jenis kendaraan · <span id="lbl">–</span></div></div></div>
@include('partials.filter', ['fields' => ['bidang', 'jenis', 'tahun', 'bulan', 'hari'], 'exportable' => true])
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card-dms kpi h-100"><div class="kpi-label">Availability rate</div><div class="kpi-value" id="kAr">–</div></div></div>
    <div class="col-6 col-lg-3"><div class="card-dms kpi h-100"><div class="kpi-label">Unit</div><div class="kpi-value" id="kUnit">–</div></div></div>
    <div class="col-6 col-lg-3"><div class="card-dms kpi h-100"><div class="kpi-label">Jam jalan</div><div class="kpi-value" id="kJalan">–</div></div></div>
    <div class="col-6 col-lg-3"><div class="card-dms kpi h-100"><div class="kpi-label">Jam perbaikan + PM</div><div class="kpi-value" id="kPerb">–</div></div></div>
</div>
<div class="card-dms"><div class="card-body"><div class="table-wrap" style="max-height:70vh">
    <table class="table table-dms table-sm table-hover sticky-head" id="tbl">
        <thead><tr><th>Bidang</th><th>Jenis</th><th class="text-end">Unit</th><th class="text-end">Unit-hari</th><th class="text-end">Jalan</th><th class="text-end">Standby</th><th class="text-end">Rusak</th><th class="text-end">Perbaikan</th><th class="text-end">PM</th><th class="text-end">Total Jam</th><th class="text-end">Availability</th><th class="text-end">Rata² Jalan</th><th class="text-end">Rata² Standby</th><th class="text-end">Rata² Rusak</th><th class="text-end">Utilisasi</th></tr></thead>
        <tbody></tbody>
    </table>
</div>
<div class="small text-secondary mt-2">Rata² = rata-rata jumlah unit per hari dengan status tsb. Utilisasi = unit-hari berstatus jalan ÷ seluruh unit-hari.</div>
</div></div>
@include('partials.dash-scripts', ['page' => ['module' => 'general', 'url' => route('api.dashboard.general')]])
@endsection
