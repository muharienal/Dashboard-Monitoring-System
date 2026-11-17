{{-- Pesan dari server, ditampilkan sebagai toast oleh assets/js/alert.js. --}}
@foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'] as $kunci => $tipe)
    @if (is_string(session($kunci)) && session($kunci) !== '')
        <div class="dms-flash" data-type="{{ $tipe }}" hidden>{{ session($kunci) }}</div>
    @endif
@endforeach
@if ($errors->any())
    <div class="dms-flash" data-type="danger" data-title="{{ $judulError ?? 'Belum tersimpan' }}" hidden>
        @if ($errors->count() === 1){{ $errors->first() }}@else<ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
    </div>
@endif
