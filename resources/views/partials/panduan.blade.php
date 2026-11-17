@php
    $petaPanduan = [
        'beranda' => ['beranda', 'Beranda'], 'input.show' => ['input', 'Input data'], 'dashboard.summary' => ['bidang', 'Dashboard bidang'],
        'dashboard.perbandingan' => ['perbandingan', 'Perbandingan bidang'], 'dashboard.availability' => ['availability', 'Availability'],
        'dashboard.utilisasi' => ['utilisasi', 'Utilisasi'], 'dashboard.maintenance' => ['perbaikan', 'Perbaikan'], 'dashboard.laporan' => ['laporan', 'Laporan'],
        'kendaraan.profil' => ['profil-kendaraan', 'Profil kendaraan'], 'master.kendaraan' => ['kendaraan', 'Kendaraan'],
        'riwayat.index' => ['riwayat', 'Riwayat'], 'akun.index' => ['akun', 'Akun'], 'profil' => ['password', 'Ganti password'],
    ];
    $panduan = $petaPanduan[request()->route()?->getName()] ?? null;
@endphp
@if ($panduan)
<div class="offcanvas offcanvas-end panduan" tabindex="-1" id="panduan" aria-labelledby="panduanJudul">
    <div class="offcanvas-header">
        <div><span class="panduan-sub">Panduan</span><h2 class="offcanvas-title" id="panduanJudul">{{ $panduan[1] }}</h2></div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>
    <div class="offcanvas-body">@include('panduan.'.$panduan[0])</div>
</div>
@endif
