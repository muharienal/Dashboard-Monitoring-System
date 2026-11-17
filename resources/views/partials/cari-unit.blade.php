{{-- Kotak cari nomor kendaraan → profil unit. Perilaku di assets/js/app.js (DMS.cariUnit). --}}
<form class="cari-unit {{ $kelas ?? '' }}" role="search" action="{{ route('kendaraan.cari') }}" onsubmit="return false" autocomplete="off">
    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
    <input type="search" name="q" placeholder="Cari nomor unit…" aria-label="Cari nomor kendaraan" role="combobox"
           aria-autocomplete="list" aria-expanded="false" aria-controls="{{ $id }}-hasil" spellcheck="false" autocapitalize="characters" enterkeyhint="search">
    <kbd class="cari-pintas" aria-hidden="true">/</kbd>
    <ul class="cari-hasil" id="{{ $id }}-hasil" role="listbox" hidden></ul>
</form>
