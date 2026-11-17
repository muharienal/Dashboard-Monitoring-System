@extends('layouts.app')
@section('title', 'Input '.$bidang->nama_tampil)

@section('content')
<div class="page-head">
    <div>
        <h1>Input Data · {{ $bidang->nama_tampil }}</h1>
        <div class="sub">Isi jam per kendaraan. Standby terisi otomatis.</div>
    </div>
    <div class="d-flex gap-2">
        @if ($kolomEdit)<button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalImport"><i class="fas fa-file-import"></i> Import</button>@endif
        <button class="btn btn-sm btn-outline-secondary" id="btnExport"><i class="fas fa-file-excel"></i> Ekspor Excel</button>
        @if (auth()->user()->isAdmin())
            <a class="btn btn-sm btn-outline-secondary" id="lnkRiwayat" href="{{ route('riwayat.index', ['bidang' => $bidang->kode]) }}" title="Riwayat perubahan bidang ini pada tanggal yang dipilih"
               onclick="this.href = '{{ route('riwayat.index') }}?' + new URLSearchParams({ bidang: '{{ $bidang->kode }}', tanggal: document.getElementById('tanggal').value })"><i class="fas fa-clock-rotate-left"></i> Riwayat</a>
        @endif
    </div>
</div>

<div class="card-dms">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
            <div>
                <label class="form-label small text-secondary mb-1" for="tanggal">Tanggal</label>
                @php
                    $tglMinta = is_string(request('tanggal')) ? request('tanggal') : '';
                    $tglAwal = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglMinta) && checkdate((int) substr($tglMinta, 5, 2), (int) substr($tglMinta, 8, 2), (int) substr($tglMinta, 0, 4)) && $tglMinta <= now()->toDateString())
                        ? $tglMinta : now()->toDateString();
                @endphp
                <input type="date" class="form-control form-control-sm" id="tanggal" max="{{ now()->toDateString() }}" value="{{ $tglAwal }}">
            </div>
            <div class="btn-group btn-group-sm" role="group" aria-label="Pindah tanggal">
                <button class="btn btn-outline-secondary" id="prevDay" title="Hari sebelumnya" aria-label="Hari sebelumnya"><i class="fas fa-chevron-left"></i></button>
                <button class="btn btn-outline-secondary" id="today">Hari ini</button>
                <button class="btn btn-outline-secondary" id="nextDay" title="Hari berikutnya" aria-label="Hari berikutnya"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="flex-grow-1" style="min-width:180px;max-width:300px">
                <label class="form-label small text-secondary mb-1" for="search">Cari kendaraan</label>
                <input type="search" class="form-control form-control-sm" id="search" placeholder="Nomor atau jenis">
            </div>
            <div>
                <label class="form-label small text-secondary mb-1" for="fJenis">Jenis</label>
                <select class="form-select form-select-sm" id="fJenis"><option value="">Semua</option></select>
            </div>
            <div>
                <label class="form-label small text-secondary mb-1" for="fTampil">Tampilkan</label>
                <select class="form-select form-select-sm" id="fTampil">
                    <option value="">Semua</option>
                    <option value="belum">Belum tersimpan</option>
                    <option value="diubah">Diubah, belum disimpan</option>
                    <option value="jalan">Ada jam jalan</option>
                    <option value="rusak">Ada rusak / perbaikan / PM</option>
                    <option value="standby">Standby penuh 24 jam</option>
                </select>
            </div>
            <div class="small text-secondary ms-auto pb-2" id="infoTampil" aria-live="polite"></div>
        </div>

        <div id="offlineBanner" class="alert alert-warning d-none d-flex align-items-center gap-2 py-2" role="status">
            <i class="fas fa-wifi"></i><span><strong>Tidak ada sinyal.</strong> Isian tetap tersimpan sementara di perangkat ini. Tekan Simpan saat sinyal kembali.</span>
        </div>
        <div id="draftBanner" class="alert alert-info d-none d-flex flex-wrap align-items-center justify-content-between gap-2 py-2" role="status">
            <span><i class="fas fa-floppy-disk"></i> <span id="draftText"></span></span>
            <span class="d-flex gap-2"><button class="btn btn-sm btn-dms" id="btnDraftPulih">Pulihkan</button><button class="btn btn-sm btn-outline-secondary" id="btnDraftBuang">Buang</button></span>
        </div>
        <div id="conflictBanner" class="alert alert-warning d-none d-flex align-items-center justify-content-between gap-2 py-2">
            <span><i class="fas fa-triangle-exclamation"></i> <span id="conflictText">Data ini baru diubah orang lain.</span></span>
            <button class="btn btn-sm btn-warning" id="btnMerge">Muat data terbaru</button>
        </div>

        <div class="table-wrap grid-wrap">
            <table class="table table-dms table-sm grid-input sticky-head" id="grid">
                <thead>
                <tr>
                    <th class="text-end">No</th><th>Jenis</th><th>Nomor</th>
                    <th class="text-end" data-k="jalan">Jalan</th><th class="text-end">Standby</th><th class="text-end" data-k="rusak">Rusak</th>
                    <th class="text-end" data-k="perbaikan">Perbaikan</th><th class="text-end" data-k="pm">PM</th><th class="text-end">Total</th>
                </tr>
                </thead>
                <tbody><tr><td colspan="9" class="empty-state">Memuat…</td></tr></tbody>
                <tfoot class="table-light fw-bold"></tfoot>
            </table>
        </div>
        {{-- Tampilan kartu untuk layar HP (diisi oleh input.js) --}}
        <div class="kartu-list" id="kartuList" aria-label="Daftar kendaraan"></div>
        <div class="kartu-total" id="kartuTotal"></div>

        <div class="status-bar">
            <div>
                <strong id="infoSaved">–</strong>
                <span class="sb-ekstra"><span class="mx-1">·</span> Update terakhir: <span id="infoLast">–</span>
                <span class="ms-2 text-nowrap"><span class="legend-dot" style="background:#fff3cd;border:1px solid #e9c46a"></span>belum disimpan</span></span>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <button class="btn btn-outline-secondary" id="btnReload" title="Muat ulang"><i class="fas fa-rotate"></i></button>
                <button class="btn btn-dms" id="btnSave" @disabled(! $kolomEdit)><i class="fas fa-floppy-disk"></i> Simpan</button>
            </div>
        </div>
    </div>
</div>

{{-- Ekspor Excel: pilih rentang tanggal --}}
<div class="modal fade" id="modalEkspor" tabindex="-1" aria-labelledby="ekJudul" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content" id="formEkspor" novalidate>
        <div class="modal-header"><h2 class="modal-title h5" id="ekJudul">Ekspor Excel · {{ $bidang->nama_tampil }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
        <div class="modal-body">
            <span class="form-label d-block" id="ekCepatLbl">Pilih cepat</span>
            <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-labelledby="ekCepatLbl">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-ek="ini">Tanggal ini</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-ek="7">7 hari terakhir</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-ek="bulan">Bulan ini</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-ek="lalu">Bulan lalu</button>
            </div>
            <div class="row g-2">
                <div class="col-6"><label class="form-label" for="ekDari">Dari tanggal</label><input type="date" class="form-control" id="ekDari" max="{{ now()->toDateString() }}" required></div>
                <div class="col-6"><label class="form-label" for="ekSampai">Sampai tanggal</label><input type="date" class="form-control" id="ekSampai" max="{{ now()->toDateString() }}" required></div>
            </div>
            <div class="form-text mt-2" id="ekInfo"></div>
            <div class="alert alert-warning py-2 px-3 mt-2 mb-0 small d-none" id="ekBelum"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Ada isian yang belum disimpan. Simpan dulu supaya ikut terekspor.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-dms" id="ekOk"><i class="fas fa-file-excel" aria-hidden="true"></i> Unduh Excel</button></div>
    </form></div>
</div>

<div class="modal fade" id="modalImport" tabindex="-1" aria-labelledby="impTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5" id="impTitle">Import dari Excel</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <ol class="ps-3 mb-3">
                    <li class="mb-1"><button type="button" class="btn btn-link p-0 align-baseline" id="btnTemplate">Unduh template</button> untuk tanggal <b id="impTanggal">–</b> (berisi semua unit bidang ini), isi jamnya di Excel.</li>
                    <li class="mb-1">Pilih file. Kolom yang dibaca: <b>Nomor</b>, <span id="impKolom"></span>. Kolom <b>Tanggal</b> boleh ada; bila ada, harus sama dengan tanggal di atas.</li>
                    <li>Periksa hasilnya, klik <b>Terapkan</b>, lalu <b>Simpan</b> seperti biasa.</li>
                </ol>
                <input type="file" class="form-control" id="impFile" accept=".xlsx,.xls,.csv">
                <div id="impHasil" class="mt-3" aria-live="polite"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-dms" id="btnTerapkan" disabled>Terapkan</button></div>
        </div>
    </div>
</div>
@endsection

@push('vendor')
<script src="{{ asset('assets/vendor/xlsx/xlsx.full.min.js') }}" defer></script>
@endpush

@push('scripts')
<script>
window.INPUT_CFG = {
    dataUrl: @json(route('input.data', $bidang)),
    eksporUrl: @json(route('input.ekspor', $bidang)),
    saveUrl: @json(route('input.simpan', $bidang)),
    bidang: @json($bidang->nama_tampil),
    kode: @json($bidang->kode),
    userId: {{ (int) auth()->id() }},
};
</script>
<script src="{{ asset('assets/js/input.js') }}?v={{ filemtime(public_path('assets/js/input.js')) }}"></script>
@endpush
