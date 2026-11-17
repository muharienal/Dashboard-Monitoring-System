@extends('layouts.app')
@section('title', 'Laporan')
@section('content')
<div class="page-head">
    <div><h1>Laporan</h1><div class="sub" id="lbl">–</div></div>
    @include('partials.filter', ['fields' => ['cari', 'bidang', 'jenis', 'tahun', 'bulan', 'hari'], 'exportable' => true, 'pdf' => true, 'cariPlaceholder' => 'Nomor, jenis, atau bidang'])
</div>
<div class="mb-3">
    <nav class="seg no-print" aria-label="Jenis laporan">
        <a href="#rekap" data-tab="rekap" class="{{ $tab === 'rekap' ? 'active' : '' }}">Rekap</a>
        <a href="#detail" data-tab="detail" class="{{ $tab === 'detail' ? 'active' : '' }}">Detail per kendaraan</a>
    </nav>
</div>

<div class="card-dms">
    <div class="card-body">
        <div id="paneRekap" class="{{ $tab === 'rekap' ? '' : 'd-none' }}">
            <div class="table-wrap" style="max-height:70vh">
                <table class="table table-dms table-sm table-hover sticky-head" id="tblRekap">
                    <thead><tr><th class="text-end" style="width:52px">No</th><th>Bidang</th><th>Jenis</th><th class="text-end">Unit</th><th class="text-end">Jalan (jam)</th><th class="text-end">Standby (jam)</th><th class="text-end">Rusak (jam)</th><th class="text-end">Perbaikan (jam)</th><th class="text-end">PM (jam)</th><th class="text-end">Availability</th><th class="text-end">Utilisasi</th></tr></thead>
                    <tbody></tbody><tfoot class="table-light fw-bold"></tfoot>
                </table>
            </div>
        </div>
        <div id="paneDetail" class="{{ $tab === 'detail' ? '' : 'd-none' }}"></div>
    </div>
</div>
@include('partials.dash-scripts', ['page' => ['module' => 'laporan', 'tab' => $tab, 'urlRekap' => route('api.dashboard.general'), 'urlDetail' => route('api.dashboard.report')]])
@endsection
