<nav class="seg mb-3" aria-label="Jenis riwayat">
    <a href="{{ route('riwayat.index') }}" class="{{ $tab === 'input' ? 'active' : '' }}" @if ($tab === 'input') aria-current="page" @endif>Data input</a>
    <a href="{{ route('riwayat.index', ['tab' => 'master']) }}" class="{{ $tab === 'master' ? 'active' : '' }}" @if ($tab === 'master') aria-current="page" @endif>Data master</a>
    <a href="{{ route('riwayat.index', ['tab' => 'login']) }}" class="{{ $tab === 'login' ? 'active' : '' }}" @if ($tab === 'login') aria-current="page" @endif>Login</a>
</nav>
