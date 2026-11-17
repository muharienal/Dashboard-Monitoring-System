@php
    $u = auth()->user();
    $bidangMenu = \App\Models\Bidang::ordered();
    $current = request()->route('bidang');
    $currentKode = $current instanceof \App\Models\Bidang ? $current->kode : $current;
    $inputOpen = request()->routeIs('input.*');
    $bidangOpen = request()->routeIs('dashboard.summary');
    $link = fn ($route, $icon, $label, $active = null) => sprintf(
        '<a href="%s" class="nav-link %s"%s><i class="fas %s icon"></i> %s</a>',
        route($route), request()->routeIs($active ?? $route) ? 'active' : '',
        request()->routeIs($active ?? $route) ? ' aria-current="page"' : '', $icon, e($label)
    );
@endphp
<nav class="sidenav" id="sidebar" aria-label="Menu utama">
    <a class="logo-card" href="{{ route('beranda') }}" aria-label="DMS v2 - ke Beranda">
        @include('partials.logo-dms')
        <span class="logo-sep" aria-hidden="true"></span>
        <img src="{{ asset('assets/img/LOGO-CORP.png') }}" alt="PT Petrokopindo Cipta Selaras">
    </a>

    @include('partials.cari-unit', ['id' => 'cariSidebar', 'kelas' => 'di-sidebar'])

    <div class="nav-scroll">
        {!! $link('beranda', 'fa-house', 'Beranda') !!}

        @if ($u->canInputAny())
            <button class="nav-link {{ $inputOpen ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#menuInput" aria-expanded="{{ $inputOpen ? 'true' : 'false' }}" aria-controls="menuInput">
                <i class="fas fa-pen-to-square icon"></i> Input Data <i class="fas fa-chevron-down chev"></i>
            </button>
            <ul class="submenu collapse {{ $inputOpen ? 'show' : '' }}" id="menuInput">
                @foreach ($bidangMenu as $b)
                    @if ($u->canInput($b))
                        <li><a href="{{ route('input.show', $b) }}" class="{{ $inputOpen && $currentKode === $b->kode ? 'active' : '' }}">{{ $b->nama_tampil }}</a></li>
                    @endif
                @endforeach
            </ul>
        @endif

        <button class="nav-link {{ $bidangOpen ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#menuBidang" aria-expanded="{{ $bidangOpen ? 'true' : 'false' }}" aria-controls="menuBidang">
            <i class="fas fa-table-cells-large icon"></i> Dashboard Bidang <i class="fas fa-chevron-down chev"></i>
        </button>
        <ul class="submenu collapse {{ $bidangOpen ? 'show' : '' }}" id="menuBidang">
            @foreach ($bidangMenu as $b)
                <li><a href="{{ route('dashboard.summary', $b) }}" class="{{ $bidangOpen && $currentKode === $b->kode ? 'active' : '' }}">{{ $b->nama_tampil }}</a></li>
            @endforeach
        </ul>

        <div class="nav-section">Semua bidang</div>
        {!! $link('dashboard.utilisasi', 'fa-truck-fast', 'Utilisasi') !!}
        {!! $link('dashboard.availability', 'fa-chart-pie', 'Availability') !!}
        {!! $link('dashboard.maintenance', 'fa-screwdriver-wrench', 'Perbaikan') !!}
        {!! $link('dashboard.laporan', 'fa-table', 'Laporan') !!}
        {!! $link('dashboard.perbandingan', 'fa-ranking-star', 'Perbandingan') !!}
        @unless ($u->isAdmin())
            {!! $link('riwayat.index', 'fa-clock-rotate-left', 'Riwayat') !!}
        @endunless

        @if ($u->isAdmin())
            <div class="nav-section">Admin</div>
            {!! $link('master.kendaraan', 'fa-truck-ramp-box', 'Kendaraan', 'master.*') !!}
            {!! $link('riwayat.index', 'fa-clock-rotate-left', 'Riwayat') !!}
            <a href="{{ route('tv') }}" class="nav-link" target="_blank" rel="noopener"><i class="fas fa-tv icon"></i> Mode TV <i class="fas fa-up-right-from-square small opacity-50 ms-auto" aria-hidden="true"></i></a>
            @php($resetMenunggu = \App\Models\PermintaanResetPassword::menunggu()->count())
            <a href="{{ route('akun.index') }}" class="nav-link {{ request()->routeIs('akun.*') ? 'active' : '' }}" @if (request()->routeIs('akun.*')) aria-current="page" @endif>
                <i class="fas fa-users-gear icon"></i> Akun
                @if ($resetMenunggu)<span class="nav-badge" title="{{ $resetMenunggu }} permintaan reset password">{{ $resetMenunggu }}<span class="visually-hidden"> permintaan reset password</span></span>@endif
            </a>
        @endif
    </div>

    <div class="sidenav-foot dropup">
        <button class="account-btn {{ request()->routeIs('profil') ? 'active' : '' }}" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu akun">
            <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</span>
            <span class="who"><strong>{{ $u->name }}</strong><span>{{ $u->role_label }}{{ $u->bidang ? ' · '.$u->bidang->nama_tampil : '' }}</span></span>
            <i class="fas fa-ellipsis-vertical more" aria-hidden="true"></i>
        </button>
        <ul class="dropdown-menu account-menu">
            <li><a class="dropdown-item" href="{{ route('profil') }}"><i class="fas fa-key"></i> Ganti password</a></li>
            <li id="itemPasang" hidden><button type="button" class="dropdown-item" id="btnPasang"><i class="fas fa-mobile-screen-button"></i> Pasang DMS v2 di perangkat ini</button></li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="POST" action="{{ route('logout') }}" data-confirm="Anda perlu masuk lagi untuk membuka DMS v2." data-confirm-title="Keluar dari DMS v2?" data-confirm-ok="Keluar" data-confirm-tone="warning" data-confirm-icon="fa-right-from-bracket">
                    @csrf
                    <button type="submit" class="dropdown-item text-danger-dms"><i class="fas fa-right-from-bracket"></i> Keluar</button>
                </form>
            </li>
        </ul>
        <div class="ver">DMS v2</div>
    </div>
</nav>
