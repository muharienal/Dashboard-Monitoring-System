{{--
    Tombol "?" kecil di samping judul: penjelasan singkat (dan rumus bila ada) dalam popover.
    Pemakaian: @include('partials.info', ['judul' => 'Availability', 'isi' => 'Teks penjelasan, boleh <b>tebal</b> dan <br>.'])
    Muncul saat diarahkan mouse, diketuk (HP), atau difokus lewat keyboard; hilang saat klik di tempat lain.
--}}
<a tabindex="0" role="button" class="info-q {{ $kelas ?? '' }}" data-bs-toggle="popover" data-bs-trigger="hover focus"
   data-bs-placement="{{ $posisi ?? 'top' }}" data-bs-html="true" data-bs-custom-class="info-pop"
   data-bs-title="{{ $judul }}" data-bs-content="{{ $isi }}" aria-label="Penjelasan: {{ $judul }}"><span aria-hidden="true">?</span></a>
