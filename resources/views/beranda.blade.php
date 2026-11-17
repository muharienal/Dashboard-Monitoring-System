@extends('layouts.app')
@section('title', 'Beranda')
@php
    $tahunList = range(now()->year, 2024);
    $rumus = fn ($t) => '<span class="rumus">'.$t.'</span>';
    $pembanding = '<br><br><b>▲▼</b> = perubahan dari periode yang sama bulan lalu. Hijau membaik, merah memburuk.';
    $info = [
        'availability' => 'Porsi waktu kendaraan <b>siap dipakai</b> (Jalan + Standby) dari seluruh jam tercatat. Makin tinggi makin baik.'.$rumus('(Jam Jalan + Jam Standby) ÷ Total jam × 100%').$pembanding,
        'utilisasi' => 'Seberapa sering kendaraan <b>benar-benar bekerja</b>. Satu unit dihitung Jalan pada suatu hari bila Jalan lebih dari 4 jam tanpa rusak/perbaikan/PM, atau Jalan adalah jam terbesar hari itu.'.$rumus('Unit-hari berstatus Jalan ÷ Seluruh unit-hari × 100%').$pembanding,
        'kelengkapan' => 'Seberapa lengkap data harian sudah diisi petugas bulan ini.'.$rumus('Unit-hari terisi ÷ Unit-hari yang seharusnya diisi × 100%').'Di bawah 100% berarti angka di halaman ini <b>belum final</b>. Hanya tampil bila satu bulan dipilih.',
        'unit' => 'Banyaknya kendaraan <b>berbeda</b> yang datanya tercatat pada periode ini. Bukan jumlah unit di data master: unit yang belum pernah diinput belum terhitung (lihat kolom <b>Unit aktif</b> di tabel per bidang).'.$pembanding,
        'tren' => 'Jumlah unit berstatus Jalan, Standby, dan Rusak (termasuk Perbaikan & PM) setiap tanggal. <b>Area kuning</b> = input tanggal itu belum lengkap, angkanya masih bisa berubah.',
    ];
    $q = fn ($k, $judul, $posisi = 'top') => view('partials.info', ['judul' => $judul, 'isi' => $info[$k], 'posisi' => $posisi])->render();
@endphp
@section('content')
<div class="page-head">
    <div><h1>Beranda</h1><div class="sub" id="periodeLbl">–</div></div>
    @include('partials.filter', ['fields' => ['tahun', 'bulan'], 'pdf' => true])
</div>

<div id="resetNotice"></div>
<section class="kelengkapan d-none" id="kelengkapan" aria-labelledby="klJudul" aria-live="polite"></section>

{{-- Ringkasan dalam kalimat: dibaca manajemen tanpa harus membuka grafik --}}
<section class="ringkasan card-dms" aria-labelledby="rkJudul">
    <div class="card-head"><h2 id="rkJudul"><i class="fas fa-file-lines" aria-hidden="true"></i> Ringkasan <span id="rkPeriode"></span></h2>
        <span class="hint" id="rkDiperbarui"></span></div>
    <div class="card-body"><ul class="rk-list" id="rkIsi"><li class="text-secondary">Memuat…</li></ul></div>
</section>

{{-- Angka utama (Jam rusak & PM ada di legenda Komposisi jam) --}}
<div class="kpi-strip kpi-6">
    <div class="kpi"><div class="kpi-label">Availability{!! $q('availability', 'Availability') !!}</div><div class="kpi-value" id="kpiAr">–</div><div class="kpi-detail" id="xAr"></div><div class="kpi-delta" id="dAr"></div></div>
    <div class="kpi"><div class="kpi-label">Utilisasi{!! $q('utilisasi', 'Utilisasi') !!}</div><div class="kpi-value" id="kpiUtil">–</div><div class="kpi-detail" id="xUtil"></div><div class="kpi-delta" id="dUtil"></div></div>
    <div class="kpi"><div class="kpi-label">Jumlah unit{!! $q('unit', 'Jumlah unit') !!}</div><div class="kpi-value" id="kpiUnit">–</div><div class="kpi-detail" id="xUnit"></div><div class="kpi-delta" id="dUnit"></div></div>
    <div class="kpi"><div class="kpi-label">Unit Jalan per hari</div><div class="kpi-value" id="kpiJalan">–</div><div class="kpi-detail">rata-rata</div><div class="kpi-delta" id="dJalan"></div></div>
    <div class="kpi"><div class="kpi-label">Jam perbaikan</div><div class="kpi-value" id="kpiPerbaikan">–</div><div class="kpi-detail" id="xPerbaikan"></div><div class="kpi-delta" id="dPerbaikan"></div></div>
    <div class="kpi"><div class="kpi-label">Kelengkapan input{!! $q('kelengkapan', 'Kelengkapan input', 'left') !!}</div><div class="kpi-value" id="kpiLengkap">–</div><div class="kpi-detail" id="xLengkap"></div></div>
</div>

{{-- Per bidang --}}
<div class="sec-head">
    <h2>Per bidang</h2>
    <span class="hint">Klik baris untuk membuka dashboard bidang</span>
</div>
<div class="bidang-list" id="bidangList">
    <div class="bidang-row head" aria-hidden="true">
        <span>Bidang</span><span>Unit aktif</span><span>Availability</span><span>Utilisasi</span><span>Jam perbaikan</span><span>Kelengkapan</span><span class="upd">Update terakhir</span><span></span>
    </div>
    <div class="empty-state">Memuat…</div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-dms h-100">
            <div class="card-head"><h2>Komposisi jam</h2></div>
            <div class="card-body">
                <div class="chart-box short"><canvas id="cDonut" role="img" aria-label="Komposisi jam"></canvas><div class="donut-center"><span class="v" id="donutAr">–</span><span class="l">jam total</span></div></div>
                <div class="mt-3" id="donutLegend"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card-dms h-100">
            <div class="card-head"><h2>Jumlah unit per hari{!! $q('tren', 'Jumlah unit per hari', 'left') !!}</h2></div>
            <div class="card-body"><div id="trenEmpty"></div><div class="chart-box"><canvas id="cTren" role="img" aria-label="Jumlah unit per status per hari"></canvas></div><div class="tren-catatan" id="trenNote"></div></div>
        </div>
    </div>
</div>

<div class="row g-3 mt-0">
    <div class="col-lg-6">
        <div class="card-dms h-100">
            <div class="card-head"><h2>Perbaikan terbanyak</h2>
                <a class="hint" href="{{ route('dashboard.maintenance') }}" id="topSemua">Lihat semua</a></div>
            <div class="card-body" id="topPerbaikan"><div class="empty-state">Memuat…</div></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-dms h-100">
            <div class="card-head"><h2>Availability terendah per jenis</h2>
                <a class="hint" href="{{ route('dashboard.availability') }}" id="jenisSemua">Lihat semua</a></div>
            <div class="card-body" id="jenisRendah"><div class="empty-state">Memuat…</div></div>
        </div>
    </div>
</div>

@include('partials.dash-scripts', ['page' => ['module' => 'beranda', 'url' => route('beranda.data'), 'summaryBase' => url('dashboard/summary'), 'inputBase' => url('input'), 'akunUrl' => route('akun.index').'#permintaanReset']])
@endsection
