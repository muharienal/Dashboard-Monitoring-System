{{--
    Jendela pilihan sebelum mengunduh Laporan PDF atau Excel: satu bulan / beberapa bulan, dan bidang.
    Petugas bidang terkunci ke bidangnya; admin, manajemen, dan pemeliharaan bisa memilih satu bidang atau semua.
    Dipanggil dari JS: DMS.pilihUnduh({ format: 'pdf' | 'excel', judul }) → Promise<{ dari, ke, bidang } | null>
--}}
@php
    $kunciUnduh = \App\Support\Filter::bidangTerkunci(auth()->user());
    $tahunUnduh = range(now()->year, 2024);
    $bulanUnduh = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
@endphp
<div class="modal fade" id="modalUnduh" tabindex="-1" aria-labelledby="muJudul" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content" id="formUnduh" novalidate>
        <div class="modal-header"><h2 class="modal-title h5" id="muJudul">Unduh laporan</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
        <div class="modal-body">
            <div class="mb-3">
                <span class="form-label d-block" id="unPeriodeLbl">Periode</span>
                <div class="seg seg-penuh" role="radiogroup" aria-labelledby="unPeriodeLbl">
                    <label><input type="radio" name="jenisPeriode" value="satu" checked> Satu bulan</label>
                    <label><input type="radio" name="jenisPeriode" value="rentang"> Beberapa bulan</label>
                </div>
            </div>
            <div class="row g-2 mb-3 align-items-end">
                <div class="col"><label class="form-label" for="unDariB" id="unDariLbl">Bulan</label>
                    <div class="d-flex gap-2">
                        <select class="form-select" id="unDariB">@foreach ($bulanUnduh as $i => $n)<option value="{{ $i + 1 }}">{{ $n }}</option>@endforeach</select>
                        <select class="form-select w-auto" id="unDariT" aria-label="Tahun">@foreach ($tahunUnduh as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach</select>
                    </div></div>
            </div>
            <div class="row g-2 mb-3 align-items-end" id="unKeBaris" hidden>
                <div class="col"><label class="form-label" for="unKeB">Sampai</label>
                    <div class="d-flex gap-2">
                        <select class="form-select" id="unKeB">@foreach ($bulanUnduh as $i => $n)<option value="{{ $i + 1 }}">{{ $n }}</option>@endforeach</select>
                        <select class="form-select w-auto" id="unKeT" aria-label="Tahun">@foreach ($tahunUnduh as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach</select>
                    </div></div>
            </div>
            <div class="mb-1"><label class="form-label" for="unBidang">Bidang</label>
                <select class="form-select" id="unBidang" @disabled($kunciUnduh)>
                    @if ($kunciUnduh)
                        <option value="{{ $kunciUnduh->kode }}">{{ $kunciUnduh->nama_tampil }}</option>
                    @else
                        <option value="">Semua bidang</option>
                        @foreach (\App\Models\Bidang::ordered() as $b)<option value="{{ $b->kode }}">{{ $b->nama_tampil }}</option>@endforeach
                    @endif
                </select>
                @if ($kunciUnduh)<div class="form-text">Sesuai bidang akun Anda.</div>@endif
            </div>
            <div class="form-text" id="unInfo" hidden></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-dms" id="unOk">Unduh</button></div>
    </form></div>
</div>
