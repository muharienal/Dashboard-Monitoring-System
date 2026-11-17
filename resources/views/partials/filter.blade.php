{{-- Filter dashboard, diletakkan sejajar judul halaman --}}
@php
    $fields = $fields ?? ['tahun', 'bulan', 'hari'];
    $now = now();
@endphp
<form class="filter-bar no-print" id="filterForm" onsubmit="return false" aria-label="Filter"
      @isset($jenisPerBidang) data-jenis-map='@json($jenisPerBidang)' @endisset>
    @if (in_array('cari', $fields))
        <div>
            <label class="form-label" for="f-cari">Cari</label>
            <input type="search" class="form-control form-control-sm" id="f-cari" name="cari" placeholder="{{ $cariPlaceholder ?? 'Nomor atau jenis' }}" style="min-width:170px" data-local>
        </div>
    @endif
    @if (in_array('bidang', $fields))
        <div>
            <label class="form-label" for="f-bidang">Bidang</label>
            <select class="form-select form-select-sm" id="f-bidang" name="bidang">
                <option value="">Semua</option>
                @foreach ($bidangList as $b)<option value="{{ $b->kode }}">{{ $b->nama_tampil }}</option>@endforeach
            </select>
        </div>
    @endif
    @if (in_array('jenis', $fields))
        <div>
            <label class="form-label" for="f-jenis">Jenis</label>
            <select class="form-select form-select-sm" id="f-jenis" name="jenis">
                <option value="">Semua</option>
                @foreach ($jenisList as $j)<option value="{{ $j }}">{{ $j }}</option>@endforeach
            </select>
        </div>
    @endif
    <div>
        <label class="form-label" for="f-tahun">Tahun</label>
        <select class="form-select form-select-sm" id="f-tahun" name="tahun" style="min-width:96px">
            <option value="">Semua</option>
            @foreach ($tahunList as $t)<option value="{{ $t }}" @selected($t == $now->year)>{{ $t }}</option>@endforeach
        </select>
    </div>
    @if (in_array('bulan', $fields))
        <div>
            <label class="form-label" for="f-bulan">Bulan</label>
            <select class="form-select form-select-sm" id="f-bulan" name="bulan">
                <option value="">Semua</option>
                @foreach (['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $i => $nm)
                    <option value="{{ $i + 1 }}" @selected($i + 1 == $now->month)>{{ $nm }}</option>
                @endforeach
            </select>
        </div>
    @endif
    @if (in_array('hari', $fields))
        <div>
            <label class="form-label" for="f-hari">Tanggal</label>
            <select class="form-select form-select-sm" id="f-hari" name="hari" style="min-width:86px">
                <option value="">Semua</option>
                @for ($d = 1; $d <= 31; $d++)<option value="{{ $d }}">{{ $d }}</option>@endfor
            </select>
        </div>
    @endif
    @isset($exportable)
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnExport"><i class="fas fa-file-excel"></i> Ekspor Excel</button>
    @endisset
    @isset($pdf)
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPdf" data-laporan-pdf="{{ route('laporan.bulanan') }}"
           title="Laporan siap cetak / simpan PDF"><i class="fas fa-file-pdf"></i> Laporan PDF</button>
    @endisset
</form>

