@php
    $warna = config('dms.warna');
    $STATUS = ['JALAN' => 'Jalan', 'STANDBY' => 'Standby', 'RUSAK' => 'Rusak', 'PERBAIKAN' => 'Perbaikan', 'PM' => 'PM'];
    $no = 0;
    $n0 = fn ($v) => number_format((float) $v, 0, ',', '.');
    $p1 = fn ($v) => $v === null ? '–' : number_format((float) $v, 1, ',', '.').'%';
    $u = auth()->user();
    $sampai = $berjalan ? now()->translatedFormat('j F Y') : $akhir->endOfMonth()->translatedFormat('j F Y');
    $namaBulan = fn ($m) => \Carbon\CarbonImmutable::create(2000, $m, 1)->translatedFormat('F');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan DMS v2 {{ str_replace('–', '-', $periode) }}{{ $pilih ? ' - '.$pilih->nama_tampil : '' }}</title>
    @include('partials.favicon')
    <link rel="stylesheet" href="{{ asset('assets/vendor/fontawesome/css/all.min.css') }}">
    <style>
        @font-face { font-family: 'Inria Sans'; font-weight: 400; src: url('{{ asset('assets/fonts/inria-sans-latin-400-normal.woff2') }}') format('woff2'); }
        @font-face { font-family: 'Inria Sans'; font-weight: 700; src: url('{{ asset('assets/fonts/inria-sans-latin-700-normal.woff2') }}') format('woff2'); }
        @page { size: A4 portrait; margin: 14mm 13mm 16mm; @bottom-right { content: "Halaman " counter(page) " dari " counter(pages); font: 8pt 'Inria Sans', sans-serif; color: #858b87; } }
        :root { --ink: #1d211e; --ink2: #4f5551; --muted: #858b87; --line: #d9ddd6; --line2: #eceee9; --green: #2f8a38; --gold: #c9962e; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        html { background: #e9ebe6; }
        body { margin: 0; font-family: 'Inria Sans', 'Segoe UI', Arial, sans-serif; color: var(--ink); font-size: 10pt; line-height: 1.4; }

        .toolbar { position: sticky; top: 0; z-index: 5; display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 10px 16px; background: #2a2e2b; color: #fff; }
        .toolbar form { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
        .toolbar .lbl { font-size: 13px; color: #c9cec8; }
        .toolbar select, .toolbar button, .toolbar a { font: inherit; font-size: 14px; border-radius: 8px; }
        .toolbar select { padding: 7px 10px; border: 1px solid #555; background: #363b37; color: #fff; }
        .toolbar .btn { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border: 1px solid #555; background: transparent; color: #fff; text-decoration: none; cursor: pointer; font-weight: 700; }
        .toolbar .btn.utama { background: var(--green); border-color: var(--green); }
        .toolbar .btn:hover { filter: brightness(1.12); }
        .toolbar .btn:focus-visible, .toolbar select:focus-visible { outline: 2px solid #b6e28e; outline-offset: 2px; }
        .toolbar .info { margin-left: auto; font-size: 13px; color: #c9cec8; }

        .kertas { width: 210mm; min-height: 297mm; margin: 18px auto 40px; padding: 14mm 13mm 16mm; background: #fff; box-shadow: 0 6px 30px rgba(0,0,0,.15); }

        /* Kop */
        .kop { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding-bottom: 8px; }
        .kop img.corp { height: 46px; }
        .kop .sistem { display: flex; align-items: center; gap: 10px; text-align: right; }
        .kop .sistem svg { width: 40px; height: 40px; }
        .kop .sistem strong { display: block; font-size: 10.5pt; }
        .kop .sistem span { display: block; font-size: 8.5pt; color: var(--ink2); }
        .kop-garis { border: 0; border-top: 2.5px solid var(--ink); border-bottom: 1px solid var(--ink); height: 4px; margin: 0 0 14px; }

        .judul { text-align: center; margin-bottom: 14px; }
        .judul h1 { font-size: 14pt; margin: 0; letter-spacing: .02em; }
        .judul p { margin: 2px 0 0; color: var(--ink2); }

        h2 { font-size: 11pt; margin: 18px 0 8px; padding-bottom: 4px; border-bottom: 1px solid var(--line); break-after: avoid; }
        h2 .nomor { color: var(--green); margin-right: 6px; }

        .kpi { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        .kpi div { border: 1px solid var(--line); border-radius: 6px; padding: 8px 10px; }
        .kpi small { display: block; color: var(--ink2); font-size: 8.5pt; }
        .kpi b { font-size: 15pt; font-variant-numeric: tabular-nums; }
        .kpi em { display: block; font-style: normal; color: var(--muted); font-size: 8pt; }

        .komposisi { margin-top: 10px; }
        .stack { display: flex; height: 14px; border-radius: 4px; overflow: hidden; background: var(--line2); }
        .stack span { display: block; height: 100%; }
        .legend { display: flex; flex-wrap: wrap; gap: 4px 16px; margin-top: 6px; font-size: 8.5pt; color: var(--ink2); }
        .legend i { display: inline-block; width: 9px; height: 9px; border-radius: 2px; margin-right: 5px; vertical-align: -1px; }

        table { width: 100%; border-collapse: collapse; font-size: 9pt; }
        th, td { padding: 4.5px 6px; border-bottom: 1px solid var(--line2); text-align: left; vertical-align: middle; }
        thead th { background: #f3f4f1; border-bottom: 1px solid var(--line); font-weight: 700; font-size: 8.5pt; color: var(--ink2); }
        tfoot td { font-weight: 700; border-top: 1px solid var(--line); border-bottom: 0; background: #fafbf9; }
        .r { text-align: right; font-variant-numeric: tabular-nums; }
        tr { break-inside: avoid; }
        .bar { display: inline-block; width: 52px; height: 6px; border-radius: 3px; background: var(--line2); vertical-align: middle; margin-left: 6px; overflow: hidden; }
        .bar span { display: block; height: 100%; background: var(--green); }
        .kosong { color: var(--muted); text-align: center; padding: 14px; }

        .catatan { margin-top: 12px; padding: 8px 10px; border-left: 3px solid var(--gold); background: #fbf6ea; font-size: 9pt; break-inside: avoid; }
        .catatan > b { display: block; margin-bottom: 2px; }
        .catatan ul { margin: 2px 0 0; padding-left: 16px; }
        .catatan li + li { margin-top: 2px; }

        .kaki { margin-top: 18px; padding-top: 6px; border-top: 1px solid var(--line2); color: var(--muted); font-size: 8pt; display: flex; justify-content: space-between; gap: 4px 10px; flex-wrap: wrap; }
        .kaki .hakcipta { margin-left: auto; }

        @media screen and (max-width: 820px) {
            .kertas { width: auto; min-height: 0; margin: 0; padding: 18px 16px; box-shadow: none; }
            .kpi { grid-template-columns: repeat(2, 1fr); }
            .tabel-gulir { overflow-x: auto; }
            .toolbar .info { margin-left: 0; width: 100%; }
        }
        @media print {
            html { background: #fff; }
            .toolbar { display: none; }
            .kertas { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
<div class="toolbar" role="toolbar" aria-label="Laporan">
    <form method="GET" action="{{ route('laporan.bulanan') }}" id="lbForm">
        <span class="lbl">Dari</span>
        <select name="dari_b" aria-label="Dari bulan">@foreach (range(1, 12) as $m)<option value="{{ $m }}" @selected($m === $awal->month)>{{ $namaBulan($m) }}</option>@endforeach</select>
        <select name="dari_t" aria-label="Dari tahun">@foreach ($tahunList as $t)<option value="{{ $t }}" @selected($t === $awal->year)>{{ $t }}</option>@endforeach</select>
        <span class="lbl">s.d.</span>
        <select name="ke_b" aria-label="Sampai bulan">@foreach (range(1, 12) as $m)<option value="{{ $m }}" @selected($m === $akhir->month)>{{ $namaBulan($m) }}</option>@endforeach</select>
        <select name="ke_t" aria-label="Sampai tahun">@foreach ($tahunList as $t)<option value="{{ $t }}" @selected($t === $akhir->year)>{{ $t }}</option>@endforeach</select>
        <select name="bidang" aria-label="Bidang" @disabled($terkunci)>
            @unless ($terkunci)<option value="">Semua bidang</option>@endunless
            @foreach ($terkunci ? [$terkunci] : $semuaBidang as $b)<option value="{{ $b->kode }}" @selected($pilih?->id === $b->id)>{{ $b->nama_tampil }}</option>@endforeach
        </select>
        <input type="hidden" name="dari"><input type="hidden" name="ke">
        <button type="submit" class="btn">Tampilkan</button>
    </form>
    <button type="button" class="btn utama" id="btnCetak"><i class="fas fa-file-pdf" aria-hidden="true"></i> Cetak / Simpan PDF</button>
    <a class="btn" href="{{ route('dashboard.laporan') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali ke DMS v2</a>
    <span class="info">Di jendela cetak, pilih <b>Simpan sebagai PDF</b> untuk menyimpan file.</span>
</div>

<main class="kertas">
    <header class="kop">
        <img class="corp" src="{{ asset('assets/img/LOGO-CORP.png') }}" alt="PT Petrokopindo Cipta Selaras">
        <div class="sistem">
            <div><strong>DMS v2 · Dashboard Management System</strong></div>
            @include('partials.logo-dms')
        </div>
    </header>
    <hr class="kop-garis">

    <div class="judul">
        <h1>{{ $satuBulan ? 'LAPORAN BULANAN' : 'LAPORAN' }} OPERASIONAL KENDARAAN</h1>
        <p>{{ $pilih ? 'Bidang '.$pilih->nama_tampil : 'Semua bidang' }} · Periode {{ $periode }}@if ($berjalan) · data s.d. {{ $sampai }}@endif</p>
    </div>

    @if ($belumMulai)
        <p class="kosong">Periode {{ $periode }} belum dimulai, belum ada data untuk dilaporkan.</p>
    @else
    <h2><span class="nomor">{{ ++$no }}.</span>Ringkasan</h2>
    <div class="kpi">
        <div><small>Availability</small><b>{{ $p1($total['availability_rate']) }}</b><em>(jalan + standby) ÷ total jam</em></div>
        <div><small>Utilisasi</small><b>{{ $p1($total['utilisasi']) }}</b><em>porsi unit-hari berstatus jalan</em></div>
        <div><small>Jumlah unit</small><b>{{ $n0($total['unit']) }}</b><em>seluruh unit beroperasi</em></div>
        <div><small>Jam perbaikan</small><b>{{ $n0($total['jam']['PERBAIKAN']) }}</b><em>total jam perbaikan</em></div>
    </div>
    @if ($total['total_jam'] > 0)
        <div class="komposisi" role="img" aria-label="Komposisi jam per status">
            <div class="stack">
                @foreach ($STATUS as $k => $l)
                    @php($pct = $total['jam'][$k] / $total['total_jam'] * 100)
                    @if ($pct > 0)<span style="width:{{ $pct }}%;background:{{ $warna[$k] }}"></span>@endif
                @endforeach
            </div>
            <div class="legend">
                @foreach ($STATUS as $k => $l)
                    <span><i style="background:{{ $warna[$k] }}"></i>{{ $l }} {{ $n0($total['jam'][$k]) }} jam ({{ $p1($total['jam'][$k] / $total['total_jam'] * 100) }})</span>
                @endforeach
            </div>
        </div>
    @endif

    @if (! $pilih)
    <h2><span class="nomor">{{ ++$no }}.</span>Per bidang</h2>
    <div class="tabel-gulir"><table>
        <thead><tr><th>Bidang</th><th class="r">Unit</th><th class="r">Availability</th><th class="r">Utilisasi</th>
            <th class="r">Jalan</th><th class="r">Standby</th><th class="r">Rusak</th><th class="r">Perbaikan</th><th class="r">PM</th></tr></thead>
        <tbody>
        @foreach ($bidang as $b)
            <tr>
                <td><b>{{ $b['nama'] }}</b></td>
                <td class="r">{{ $n0($b['unit']) }}</td>
                <td class="r">{{ $p1($b['availability_rate']) }}@if ($b['availability_rate'] !== null)<span class="bar" aria-hidden="true"><span style="width:{{ $b['availability_rate'] }}%"></span></span>@endif</td>
                <td class="r">{{ $p1($b['utilisasi']) }}</td>
                @foreach (['JALAN', 'STANDBY', 'RUSAK', 'PERBAIKAN', 'PM'] as $k)<td class="r">{{ $n0($b['jam'][$k]) }}</td>@endforeach
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr><td>Total</td><td class="r">{{ $n0($total['unit']) }}</td><td class="r">{{ $p1($total['availability_rate']) }}</td><td class="r">{{ $p1($total['utilisasi']) }}</td>
            @foreach (['JALAN', 'STANDBY', 'RUSAK', 'PERBAIKAN', 'PM'] as $k)<td class="r">{{ $n0($total['jam'][$k]) }}</td>@endforeach</tr></tfoot>
    </table></div>
    <div style="font-size:8pt;color:var(--muted);margin-top:4px">Kolom Jalan s.d. PM dalam jam.</div>
    @endif

    @if (! $satuBulan)
    <h2><span class="nomor">{{ ++$no }}.</span>Per bulan</h2>
    <div class="tabel-gulir"><table>
        <thead><tr><th>Bulan</th><th class="r">Unit</th><th class="r">Availability</th><th class="r">Utilisasi</th><th class="r">Rusak (jam)</th><th class="r">Perbaikan (jam)</th><th class="r">PM (jam)</th></tr></thead>
        <tbody>
        @foreach ($perBulan as $pb)
            <tr><td><b>{{ $pb['label'] }}</b></td><td class="r">{{ $n0($pb['unit']) }}</td>
                <td class="r">{{ $p1($pb['availability_rate']) }}@if ($pb['availability_rate'] !== null)<span class="bar" aria-hidden="true"><span style="width:{{ $pb['availability_rate'] }}%"></span></span>@endif</td>
                <td class="r">{{ $p1($pb['utilisasi']) }}</td><td class="r">{{ $n0($pb['jam']['RUSAK']) }}</td><td class="r">{{ $n0($pb['jam']['PERBAIKAN']) }}</td><td class="r">{{ $n0($pb['jam']['PM']) }}</td></tr>
        @endforeach
        </tbody>
    </table></div>
    @endif

    <h2><span class="nomor">{{ ++$no }}.</span>Per jenis kendaraan</h2>
    <div class="tabel-gulir"><table>
        <thead><tr><th style="width:28px" class="r">No</th><th>Jenis</th><th class="r">Unit</th><th class="r">Availability</th><th class="r">Utilisasi</th><th class="r">Rusak (jam)</th><th class="r">Perbaikan (jam)</th><th class="r">PM (jam)</th></tr></thead>
        <tbody>
        @forelse ($jenis as $i => $j)
            <tr><td class="r">{{ $i + 1 }}</td><td>{{ $j['jenis'] }}</td><td class="r">{{ $n0($j['unit']) }}</td><td class="r">{{ $p1($j['availability_rate']) }}</td><td class="r">{{ $p1($j['utilisasi']) }}</td>
                <td class="r">{{ $n0($j['jam']['RUSAK']) }}</td><td class="r">{{ $n0($j['jam']['PERBAIKAN']) }}</td><td class="r">{{ $n0($j['jam']['PM']) }}</td></tr>
        @empty
            <tr><td colspan="8" class="kosong">Belum ada data.</td></tr>
        @endforelse
        </tbody>
    </table></div>

    <h2><span class="nomor">{{ ++$no }}.</span>10 unit dengan jam perbaikan tertinggi</h2>
    <div class="tabel-gulir"><table>
        <thead><tr><th style="width:28px" class="r">No</th><th>Nomor</th><th>Jenis</th><th class="r">Perbaikan (jam)</th><th class="r">Hari perbaikan</th><th class="r">Rusak (jam)</th><th class="r">PM (jam)</th></tr></thead>
        <tbody>
        @forelse ($perbaikan as $i => $m)
            <tr><td class="r">{{ $i + 1 }}</td><td><b>{{ $m['nomor'] }}</b></td><td>{{ $m['jenis'] }}</td><td class="r">{{ $n0($m['perbaikan']) }}</td>
                <td class="r">{{ $n0($m['hari_perbaikan']) }} dari {{ $n0($m['hari']) }}</td><td class="r">{{ $n0($m['rusak']) }}</td><td class="r">{{ $n0($m['pm']) }}</td></tr>
        @empty
            <tr><td colspan="7" class="kosong">Tidak ada jam perbaikan pada periode ini.</td></tr>
        @endforelse
        </tbody>
    </table></div>

    @if ($berjalan)
        <div class="catatan">
            <b>Catatan</b>
            <ul><li>{{ $satuBulan ? 'Bulan' : 'Periode' }} masih berjalan; angka dapat berubah sampai akhir bulan.</li></ul>
        </div>
    @endif

    @endif

    <footer class="kaki">
        <span>Dicetak oleh {{ $u->name }}, {{ now()->translatedFormat('j F Y H:i') }}</span>
        @include('partials.kaki', ['kakiKelas' => 'hakcipta'])
    </footer>
</main>

<script>
    document.getElementById('btnCetak').addEventListener('click', () => window.print());
    // Pilihan bulan & tahun digabung menjadi dari=YYYY-MM & ke=YYYY-MM (parameter yang dibaca server).
    document.getElementById('lbForm').addEventListener('submit', (e) => {
        const f = e.target, dua = (n) => String(n).padStart(2, '0');
        f.dari.value = `${f.dari_t.value}-${dua(f.dari_b.value)}`;
        f.ke.value = `${f.ke_t.value}-${dua(f.ke_b.value)}`;
        ['dari_b', 'dari_t', 'ke_b', 'ke_t'].forEach((n) => { f[n].disabled = true; });
        if (!f.bidang.value || f.bidang.disabled) f.bidang.disabled = true;
    });
    // Kembali dengan tombol Back: aktifkan lagi pilihan yang tadi dinonaktifkan saat dikirim.
    window.addEventListener('pageshow', () => document.querySelectorAll('#lbForm select').forEach((el) => { el.disabled = el.name === 'bidang' && {{ $terkunci ? 'true' : 'false' }}; }));
    @if ($cetak)
        // Dibuka dari tombol "Laporan PDF": langsung tampilkan jendela cetak.
        window.addEventListener('load', () => setTimeout(() => window.print(), 300));
    @endif
</script>
</body>
</html>
